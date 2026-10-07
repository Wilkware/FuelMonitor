<?php

declare(strict_types=1);

/** General functions */
require_once __DIR__ . '/../libs/_traits.php';

/** Namespaced traits */
use Wilkware\FuelMonitor\DebugHelper;
use Wilkware\FuelMonitor\FormHelper;
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
    use FormHelper;
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

    /** @var array<string,int> Archived status variables and their aggregation type */
    private const ARCHIVE_VARIABLES = [
        'kilometers' => self::ARCHIVE_COUNTER,
        'liters'     => self::ARCHIVE_DEFAULT,
        'price'      => self::ARCHIVE_DEFAULT,
        'average'    => self::ARCHIVE_DEFAULT,
        'costs'      => self::ARCHIVE_DEFAULT,
    ];

    /** @var int Max. seconds between the archive entries of one refuelling (SetValue logs each variable separately) */
    private const HISTORY_TOLERANCE = 5;

    /** @var int Number of refuelling entries sent to the tile */
    private const HISTORY_LIMIT = 20;

    /** @var int Instance status: inactive because archiving is not (correctly) set up */
    private const STATUS_NO_ARCHIVE = 202;

    /** @var string Prefix of the WebHook used for the export download */
    private const HOOK_PREFIX = 'fuelmonitor';

    /** @var array<int,string> Column header of the import/export CSV file */
    private const CSV_HEADER = ['Date', 'Type', 'Mileage', 'Liters', 'Price', 'Average', 'Costs'];

    // -------------------------------------------------------------------------
    // Constants (Min/Max)
    // -------------------------------------------------------------------------

    /** @var string Date reset constants */
    private const DATE_RESET = '{"year": -1, "month": -1, "day": -1 }';

    // -------------------------------------------------------------------------
    // Methods
    // -------------------------------------------------------------------------

    /**
     * In contrast to Construct, this function is called only once when creating the instance and starting Symcon.
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

        // Register status variables (archiving is enabled by the user via the configuration form)
        $this->RegisterVariableInteger('kilometers', $this->Translate('Kilometers'), ['PRESENTATION' => VARIABLE_PRESENTATION_VALUE_PRESENTATION, 'SUFFIX' => ' km'], 0);
        $this->RegisterVariableFloat('liters', $this->Translate('Liters'), ['PRESENTATION' => VARIABLE_PRESENTATION_VALUE_PRESENTATION, 'SUFFIX' => ' l', 'DIGITS' => 2], 1);
        $this->RegisterVariableFloat('price', $this->Translate('Price'), ['PRESENTATION' => VARIABLE_PRESENTATION_VALUE_PRESENTATION, 'SUFFIX' => ' €', 'DIGITS' => 3], 2);
        $this->RegisterVariableFloat('average', $this->Translate('Average fuel consumption'), ['PRESENTATION' => VARIABLE_PRESENTATION_VALUE_PRESENTATION, 'SUFFIX' => ' l/100km', 'DIGITS' => 2], 3);
        $this->RegisterVariableFloat('costs', $this->Translate('Costs'), ['PRESENTATION' => VARIABLE_PRESENTATION_VALUE_PRESENTATION, 'SUFFIX' => ' €/100km', 'DIGITS' => 2], 4);

        // Attributes for partial-refuel consumption tracking
        // LastFullTankKM = odometer reading at the last full refuel (reference point)
        $this->RegisterAttributeInteger('LastFullTankKM', 0);
        // LiterSinceLastFullTank = liters accumulated since that reference point (partial refuels add up here)
        $this->RegisterAttributeFloat('LiterSinceLastFullTank', 0.0);

        // Activate HTML-SDK visualization for this instance (custom tile via GetVisualizationTile()/module.html)
        $this->SetVisualizationType(1);

        // Run the kernel dependent part of ApplyChanges as soon as the kernel is ready
        $this->RegisterMessage(0, IPS_KERNELSTARTED);
    }

    /**
     * This function is called when deleting the instance during operation and when updating via "Module Control".
     * The function is not called when exiting Symcon.
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

        // Sets one property of a named form element
        $set = function (array &$items, string $name, string $property, mixed $value): void
        {
            $this->ModifyFormElement($items, $name, function (array &$element) use ($property, $value): void
            {
                $element[$property] = $value;
            });
        };

        // Extract Version
        $instance = IPS_GetInstance($this->InstanceID);
        $modul = IPS_GetModule($instance['ModuleInfo']['ModuleID']);
        $library = IPS_GetLibrary($modul['LibraryID']);
        $set($form['actions'], 'Version', 'caption', sprintf('v%s.%d', $library['Version'], $library['Build']));

        // Archive status
        $archived = $this->IsArchiveConfigured();
        $set($form['actions'], 'ArchivePanel', 'expanded', !$archived);
        $set($form['actions'], 'ArchiveStatus', 'caption', $this->GetArchiveStatusCaption($archived));
        $set($form['actions'], 'ArchiveButton', 'visible', !$archived);
        foreach (['ImportFile', 'ImportButton', 'ExportButton'] as $name) {
            $set($form['actions'], $name, 'enabled', $archived);
        }
        $set($form['actions'], 'ImportExportHint', 'visible', !$archived);

        // Read Setup
        $tuev = $this->ReadPropertyBoolean('EnableTuev');
        $service = $this->ReadPropertyBoolean('EnableService');
        $mileage = $this->ReadPropertyBoolean('ServiceByMileage');
        $date = $this->ReadPropertyBoolean('ServiceByDate');

        // Enable or disable tuev
        $set($form['elements'], 'TuevReminderDays', 'enabled', $tuev);

        // Enable or disable service
        $set($form['elements'], 'ServiceByMileage', 'enabled', $service);
        $set($form['elements'], 'ServiceReminderKm', 'enabled', $service && $mileage);
        $set($form['elements'], 'ServiceByDate', 'enabled', $service);
        $set($form['elements'], 'ServiceReminderDays', 'enabled', $service && $date);

        // return form
        return (string) json_encode($form);
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

        // Service/TÜV due dates
        $tuev = $this->ReadPropertyBoolean('EnableTuev');
        $this->MaintainVariable('tuev_due_date', $this->Translate('TÜV due date'), VARIABLETYPE_INTEGER, ['PRESENTATION' => VARIABLE_PRESENTATION_DATE_TIME], 10, $tuev);
        $this->MaintainAction('tuev_due_date', $tuev);

        $service = $this->ReadPropertyBoolean('EnableService');
        $mileage = $this->ReadPropertyBoolean('ServiceByMileage');
        $date = $this->ReadPropertyBoolean('ServiceByDate');
        $this->MaintainVariable('service_due_mileage', $this->Translate('Service due mileage'), VARIABLETYPE_INTEGER, ['PRESENTATION' => VARIABLE_PRESENTATION_VALUE_INPUT, 'SUFFIX' => ' km'], 11, $service && $mileage);
        $this->MaintainAction('service_due_mileage', $service && $mileage);
        $this->MaintainVariable('service_due_date', $this->Translate('Service due date'), VARIABLETYPE_INTEGER, ['PRESENTATION' => VARIABLE_PRESENTATION_DATE_TIME], 12, $service && $date);
        $this->MaintainAction('service_due_date', $service && $date);

        // Everything below accesses the Archive Control or the visualization
        if (IPS_GetKernelRunlevel() !== KR_READY) {
            return;
        }
        $this->InitializeState();
    }

    /**
     * The content of the function can be overwritten in order to carry out own reactions to certain messages.
     * The function is only called for registered MessageIDs/SenderIDs combinations.
     *
     * @param int $timestamp Continuous counter timestamp
     * @param int $sender Sender ID
     * @param int $message ID of the message
     * @param array<mixed> $data Data of the message
     *
     * @return void
     */
    public function MessageSink(int $timestamp, int $sender, int $message, array $data): void
    {
        if ($message === IPS_KERNELSTARTED) {
            $this->InitializeState();
        }
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
        $this->LogDebug(__FUNCTION__, $ident . ' => ' . var_export($value, true));
        switch ($ident) {
            case 'SaveTankEntry':
                $this->OnSaveInput((string) $value);
                break;
            case 'EnableArchive':
                $this->EnableArchive();
                break;
            case 'OnImport':
                $this->OnImport((string) $value);
                break;
            case 'tuev_due_date':
            case 'service_due_date':
            case 'service_due_mileage':
                $this->SetValueInteger($ident, (int) $value);
                $this->UpdateVisualizationValue($this->GetFullUpdateMessage());
                break;
            case 'OnChangeTuev':
                $this->OnChangeTuev((bool) $value);
                break;
            case 'OnChangeService':
                $this->OnChangeService((string) $value);
                break;
            case 'OnChangeServiceByMileage':
                $this->OnChangeServiceByMileage((bool) $value);
                break;
            case 'OnChangeServiceByDate':
                $this->OnChangeServiceByDate((bool) $value);
                break;
            default:
                throw new Exception('Invalid ident: ' . $ident);
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
        // Important: $handling at the end, as the handleMessage function is only defined in the HTML
        return $module . $handling;
    }

    /**
     * This function will be called by the hook control. Delivers the refuelling data as CSV download.
     *
     * @return void
     */
    protected function ProcessHookData(): void
    {
        if (($_GET['export'] ?? '') !== 'csv') {
            http_response_code(400);
            return;
        }
        $aid = $this->GetArchiveID();
        if ($aid === 0 || !$this->IsArchiveLogging($aid)) {
            http_response_code(404);
            echo $this->Translate('Archiving is not enabled! Please enable it in the instance configuration.');
            return;
        }
        $plate = preg_replace('/[^A-Za-z0-9]+/', '', $this->ReadPropertyString('LicensePlate'));
        $filename = 'fuelmonitor_' . ($plate !== '' ? $plate : $this->InstanceID) . '_' . date('Ymd') . '.csv';
        $history = $this->WithRefuelTypes(array_reverse($this->GetHistory($aid, 0)));
        $this->LogDebug(__FUNCTION__, 'Export ' . count($history) . ' entries to ' . $filename);

        // output headers so that the file is downloaded rather than displayed
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename=' . $filename);
        $output = fopen('php://output', 'w');
        fputcsv($output, self::CSV_HEADER, ';', '"', '');
        foreach ($history as $entry) {
            fputcsv($output, [
                date('Y-m-d H:i:s', $entry['timestamp']),
                $entry['type'],
                $entry['mileage'],
                $entry['liters'],
                $entry['price'],
                round($entry['average'], 4),
                round($entry['costs'], 4),
            ], ';', '"', '');
        }
        fclose($output);
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
     * Modify service reminder.
     *
     * @param string $value JSON array [EnableService, ServiceByMileage, ServiceByDate]
     *
     * @return void
     */
    protected function OnChangeService(string $value): void
    {
        $this->LogDebug(__FUNCTION__, $value);
        $data = json_decode($value, true);
        if (!is_array($data) || count($data) !== 3) {
            return;
        }
        [$enabled, $byMileage, $byDate] = array_map('boolval', $data);
        $this->UpdateFormField('ServiceByMileage', 'enabled', $enabled);
        $this->UpdateFormField('ServiceReminderKm', 'enabled', $enabled && $byMileage);
        $this->UpdateFormField('ServiceByDate', 'enabled', $enabled);
        $this->UpdateFormField('ServiceReminderDays', 'enabled', $enabled && $byDate);
    }

    /**
     * Modify service by mileage reminder.
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
     * Modify service by date reminder.
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
     * Kernel dependent part of ApplyChanges (archive seeding, TÜV suggestion, tile refresh).
     *
     * @return void
     */
    private function InitializeState(): void
    {
        // WebHook for the export download
        $this->RegisterHook(self::HOOK_PREFIX . $this->InstanceID);
        $this->UpdateArchiveStatus();
        $this->SeedInitialMileage();

        // Suggest an initial TÜV due date from FirstRegistration + 3 years (first inspection in
        // Germany), but only ONCE - as soon as tuev_due_date has a real value, never overwrite it
        if ($this->ReadPropertyBoolean('EnableTuev')) {
            $tuevDue = $this->GetValue('tuev_due_date');
            if ($tuevDue <= 0) {
                $firstReg = $this->GetFirstRegistrationTimestamp();
                if ($firstReg > 0) {
                    $suggested = (int) strtotime('+3 years', $firstReg);
                    $this->SetValueInteger('tuev_due_date', $suggested);
                    $this->LogDebug(__FUNCTION__, 'Auto-suggested tuev_due_date: ' . date('Y-m-d', $suggested));
                }
            }
        }

        // Send a complete update message to the display, as parameters may have changed
        $this->UpdateVisualizationValue($this->GetFullUpdateMessage());
    }

    /**
     * If this is still the very first setup (archive completely empty) and an initial mileage was
     * configured (e.g. when buying a used car), seed the archive with an initial filling automatically.
     * NOTE: this only fires once - as soon as the archive holds any entry nothing happens here anymore,
     * even if InitialMileage is edited again.
     *
     * @return void
     */
    private function SeedInitialMileage(): void
    {
        $mi = $this->ReadPropertyInteger('InitialMileage');
        $aid = $this->GetArchiveID();
        if ($mi <= 0 || $aid === 0 || !$this->IsArchiveLogging($aid)) {
            return;
        }
        // remove the zero value written when the logging was enabled
        $ks = $this->GetIDForIdent('kilometers');
        $this->ArchiveCheck($aid, $ks);
        $lastValue = AC_GetLoggedValues($aid, $ks, 0, 0, 1);
        if (!empty($lastValue)) {
            return;
        }
        $this->OnSaveInput(json_encode([
            'date'     => date('Y-m-d'),
            'type'     => 0,
            'mileage'  => $mi,
            'quantity' => 0,
            'price'    => 0,
            'invoice'  => 0,
        ]));
        $this->LogDebug(__FUNCTION__, 'Auto-seeded initial filling from InitialMileage: ' . $mi);
    }

    /**
     * Enables archiving with the correct aggregation type for all archived status variables.
     * Triggered by the user via the configuration form.
     *
     * @return void
     */
    private function EnableArchive(): void
    {
        $aid = $this->GetArchiveID();
        if ($aid === 0) {
            $this->EchoMessage('Archive Control not found!');
            return;
        }
        foreach (self::ARCHIVE_VARIABLES as $ident => $type) {
            $vid = $this->GetIDForIdent($ident);
            $this->ArchiveVariable($aid, $vid, $type);
            AC_ReAggregateVariable($aid, $vid);
        }
        $this->LogDebug(__FUNCTION__, 'Archiving enabled for all status variables!');

        $archived = $this->UpdateArchiveStatus();
        $this->UpdateFormField('ArchiveStatus', 'caption', $this->GetArchiveStatusCaption($archived));
        $this->UpdateFormField('ArchiveButton', 'visible', !$archived);
        foreach (['ImportFile', 'ImportButton', 'ExportButton'] as $field) {
            $this->UpdateFormField($field, 'enabled', $archived);
        }
        $this->UpdateFormField('ImportExportHint', 'visible', !$archived);

        $this->SeedInitialMileage();
        $this->UpdateVisualizationValue($this->GetFullUpdateMessage());
    }

    /**
     * Writes a value for one of the 5 archived status variables, choosing the right path:
     * - Today's date: plain SetValue() - Archive Control logs this automatically and correctly
     *   (with real time-of-day), AND GetValue() stays live. No manual archive call needed.
     * - Backdated date: SetValue() would log at "now", not at the chosen date, so we write
     *   directly into the archive via AC_AddLoggedValues() instead. GetValue() intentionally
     *   stays behind in this case (the backdated entry isn't "the current one").
     *
     * @param int $aid Archive Control instance ID.
     * @param int $varId Variable ID of the status variable.
     * @param string $ident Ident of the status variable.
     * @param int|float $value Value to write.
     * @param bool $isFloat True if the variable is a float, false if integer.
     * @param int $ts Timestamp of the refuelling entry.
     *
     * @return void
     */
    private function WriteValue(int $aid, int $varId, string $ident, int|float $value, bool $isFloat, int $ts): void
    {
        if (date('Y-m-d', $ts) === date('Y-m-d')) {
            if ($isFloat) {
                $this->SetValueFloat($ident, (float) $value);
            } else {
                $this->SetValueInteger($ident, (int) $value);
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
     *   "type": int             // 0 = initial filling, 1 = partial refuelling, 2 = full refuelling
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
        if ($ts === false) {
            $this->LogDebug(__FUNCTION__, 'Invalid date: ' . var_export($data['date'] ?? null, true));
            echo $this->Translate('Invalid date!');
            return;
        }
        $mi = (int) ($data['mileage'] ?? 0);
        $tq = (float) ($data['quantity'] ?? 0);
        $pl = (float) ($data['price'] ?? 0);
        $iv = (float) ($data['invoice'] ?? 0);
        $rt = (int) ($data['type'] ?? 2); // 0 = initial, 1 = partial, 2 = full

        // Plausibility check instead of server-side recalculation: the HTML tile already computes
        // a self-consistent triangle (see module.html), this only guards against broken/incomplete
        // data reaching the archive. Initial filling (rt=0) is exempt - 0/0/0 is valid there (reference
        // point only, e.g. the auto-seed from InitialMileage).
        if ($rt !== 0) {
            $expected = round($tq * $pl, 2);
            if ($tq <= 0 || $pl <= 0 || $iv <= 0 || abs($expected - $iv) > 0.05) {
                $this->LogDebug(__FUNCTION__, 'Plausibility check failed: Quantity=' . $tq . ', Price=' . $pl . ', Invoice=' . $iv);
                echo $this->Translate('Quantity, price and invoice do not match!');
                return;
            }
        }

        $this->LogDebug(__FUNCTION__, 'Date: ' . $ts . ', Mileage: ' . $mi . ', Quantity: ' . $tq . ', Price: ' . $pl . ', Invoice: ' . $iv . ', Type: ' . $rt);

        // mileage is mandatory (0 is reserved for the value the archive writes when the logging is enabled)
        if ($mi <= 0) {
            echo $this->Translate('Please enter the mileage!');
            return;
        }

        // future date check
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
        $aid = $this->GetArchiveID();
        if ($aid === 0) {
            $this->LogMessage('Archive Control not found!', KL_ERROR);
            echo $this->Translate('Archive Control not found!');
            return;
        }

        // check logging status
        $status = $this->ArchiveCheck($aid, $ks);
        $status = $status && $this->ArchiveCheck($aid, $ls);
        $status = $status && $this->ArchiveCheck($aid, $ps);
        $status = $status && $this->ArchiveCheck($aid, $as);
        $status = $status && $this->ArchiveCheck($aid, $cs);
        if (!$status) {
            $this->LogMessage('Archive logging status not valid!', KL_WARNING);
            $this->SetStatus(self::STATUS_NO_ARCHIVE);
            echo $this->Translate('Archiving is not enabled! Please enable it in the instance configuration.');
            return;
        }

        // first save?
        $lastValue = AC_GetLoggedValues($aid, $ks, 0, 0, 1);
        $first = empty($lastValue);
        $this->LogDebug(__FUNCTION__, 'First Save: ' . var_export($first, true));

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

        // last known mileage comes straight from the archive (not GetValue(), backdated entries
        // are not written to the variable) - reuses $lastValue from above
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
            foreach (array_keys(self::ARCHIVE_VARIABLES) as $ident) {
                $this->ClearArchiveData($aid, $ident);
            }
            $this->LogDebug(__FUNCTION__, 'ReInit Archive - delete all old values!');
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
        $this->LogDebug(__FUNCTION__, 'Status ReAggregate: ' . var_export($status, true));
        // Refresh the tile (new history entry, updated last-known values)
        $this->UpdateVisualizationValue($this->GetFullUpdateMessage());
    }

    /**
     * Adds the refuelling type to history entries (not archived, so it is derived from the data):
     * first entry = initial filling, consumption > 0 = full refuelling, otherwise partial refuelling.
     *
     * @param array<int,array<string,mixed>> $history History entries, oldest first.
     *
     * @return array<int,array<string,mixed>> Entries with additional key "type" (0, 1 or 2).
     */
    private function WithRefuelTypes(array $history): array
    {
        foreach ($history as $index => &$entry) {
            $entry['type'] = $index === 0 ? 0 : ($entry['average'] > 0 ? 2 : 1);
        }
        unset($entry);
        return $history;
    }

    /**
     * Restores the refuelling data from a CSV file (format of the export).
     * All existing archive data of the 5 status variables is replaced!
     *
     * @param string $value Content of the CSV file (base64 coded, from SelectFile).
     *
     * @return void
     */
    private function OnImport(string $value): void
    {
        $aid = $this->GetArchiveID();
        if ($aid === 0 || !$this->IsArchiveLogging($aid)) {
            $this->EchoMessage('Archiving is not enabled! Please enable it in the instance configuration.');
            return;
        }
        $csv = base64_decode($value, true);
        if ($csv === false || trim($csv) === '') {
            $this->EchoMessage('Please select a file first!');
            return;
        }
        $entries = $this->ParseImport($csv);
        if (empty($entries)) {
            $this->EchoMessage('The file does not contain any valid refuelling entries!');
            return;
        }

        // replace the complete archive data
        $series = ['kilometers' => [], 'liters' => [], 'price' => [], 'average' => [], 'costs' => []];
        foreach ($entries as $entry) {
            $series['kilometers'][] = ['TimeStamp' => $entry['timestamp'], 'Value' => $entry['mileage']];
            $series['liters'][] = ['TimeStamp' => $entry['timestamp'], 'Value' => $entry['liters']];
            $series['price'][] = ['TimeStamp' => $entry['timestamp'], 'Value' => $entry['price']];
            $series['average'][] = ['TimeStamp' => $entry['timestamp'], 'Value' => $entry['average']];
            $series['costs'][] = ['TimeStamp' => $entry['timestamp'], 'Value' => $entry['costs']];
        }
        $lastTs = $entries[count($entries) - 1]['timestamp'];
        foreach ($series as $ident => $values) {
            $vid = $this->ClearArchiveData($aid, $ident);
            AC_AddLoggedValues($aid, $vid, $values);
            // safety net, in case the archive wrote the value of the re-enabled logging delayed
            $this->RemoveLoggedValuesSince($aid, $vid, $lastTs + 1);
            AC_ReAggregateVariable($aid, $vid);
        }

        // rebuild the reference point for the next consumption calculation
        $lastFullTankKM = 0;
        $literSum = 0.0;
        foreach ($entries as $entry) {
            if ($entry['type'] === 1) {
                $literSum += $entry['liters'];
            } else {
                $lastFullTankKM = $entry['mileage'];
                $literSum = 0.0;
            }
        }
        $this->WriteAttributeInteger('LastFullTankKM', $lastFullTankKM);
        $this->WriteAttributeFloat('LiterSinceLastFullTank', $literSum);
        $this->LogDebug(__FUNCTION__, 'Imported ' . count($entries) . ' entries, LastFullTankKM => ' . $lastFullTankKM . ', LiterSinceLastFullTank => ' . $literSum);

        $this->UpdateVisualizationValue($this->GetFullUpdateMessage());
        $this->EchoMessage(sprintf($this->Translate('%d refuelling entries imported.'), count($entries)));
    }

    /**
     * Parses and validates the CSV content of an import. Accepts ";" or "," as separator and,
     * with ";", also a decimal comma (e.g. after editing the file with a German Excel).
     *
     * @param string $csv CSV content.
     *
     * @return array<int,array{timestamp:int,type:int,mileage:int,liters:float,price:float,average:float,costs:float}> Entries, oldest first.
     */
    private function ParseImport(string $csv): array
    {
        $csv = preg_replace('/^\xEF\xBB\xBF/', '', $csv);
        $lines = preg_split('/\r\n|\r|\n/', trim($csv));
        $separator = substr_count($lines[0], ';') >= substr_count($lines[0], ',') ? ';' : ',';
        $number = fn (string $v): float => (float) ($separator === ';' ? str_replace(',', '.', $v) : $v);

        $entries = [];
        foreach ($lines as $nr => $line) {
            $row = str_getcsv($line, $separator, '"', '');
            if (count($row) < count(self::CSV_HEADER) || $row[0] === self::CSV_HEADER[0]) {
                continue;
            }
            $ts = strtotime(trim($row[0]));
            $type = (int) $row[1];
            if ($ts === false || $ts > time() || !in_array($type, [0, 1, 2], true) || (int) $row[2] <= 0) {
                $this->LogDebug(__FUNCTION__, 'Line ' . ($nr + 1) . ' skipped: ' . $line);
                continue;
            }
            $entries[$ts] = [
                'timestamp' => $ts,
                'type'      => $type,
                'mileage'   => (int) $row[2],
                'liters'    => $number($row[3]),
                'price'     => $number($row[4]),
                'average'   => $number($row[5]),
                'costs'     => $number($row[6]),
            ];
        }
        ksort($entries);
        return array_values($entries);
    }

    /**
     * Deletes all archived data of a status variable. Deleting all data also disables the logging,
     * so logging and aggregation type are restored afterwards (the user had enabled it before).
     *
     * @param int $aid Archive Control instance ID.
     * @param string $ident Ident of the status variable.
     *
     * @return int Variable ID.
     */
    private function ClearArchiveData(int $aid, string $ident): int
    {
        $vid = $this->GetIDForIdent($ident);
        AC_DeleteVariableData($aid, $vid, 0, 0);
        $this->ArchiveVariable($aid, $vid, self::ARCHIVE_VARIABLES[$ident]);
        // Enabling the logging writes the current variable value as first entry - remove it again
        $this->RemoveLoggedValuesSince($aid, $vid, 0);
        return $vid;
    }

    /**
     * Removes all logged values of a variable from the given timestamp up to now.
     * A partial deletion (start > 0) keeps the logging active, in contrast to deleting all data.
     *
     * @param int $aid Archive Control instance ID.
     * @param int $vid Variable ID.
     * @param int $since Start timestamp (inclusive).
     *
     * @return void
     */
    private function RemoveLoggedValuesSince(int $aid, int $vid, int $since): void
    {
        $rows = AC_GetLoggedValues($aid, $vid, $since, 0, 0);
        if (empty($rows)) {
            return;
        }
        // rows are sorted newest first
        AC_DeleteVariableData($aid, $vid, (int) $rows[count($rows) - 1]['TimeStamp'], 0);
        $this->LogDebug(__FUNCTION__, count($rows) . ' value(s) of #' . $vid . ' removed since ' . date('Y-m-d H:i:s', $since));
    }

    /**
     * Enables logging for a variable and sets its aggregation type.
     *
     * @param int $ac Archive Control ID
     * @param int $var Variable ID
     * @param int $type Aggregation type (ARCHIVE_DEFAULT or ARCHIVE_COUNTER)
     *
     * @return void
     */
    private function ArchiveVariable(int $ac, int $var, int $type): void
    {
        AC_SetLoggingStatus($ac, $var, true);
        AC_SetAggregationType($ac, $var, $type);
        if ($type === self::ARCHIVE_COUNTER) {
            AC_SetCounterIgnoreZeros($ac, $var, true);
        }
    }

    /**
     * Checks the logging status of a variable and removes the zero value written when the logging
     * was enabled - but only if it is the one and only value in the archive (a zero after a partial
     * refuelling is a real value and must stay).
     *
     * @param int $ac Archive Control ID
     * @param int $var Variable ID
     *
     * @return bool True if enabled, otherwise false.
     */
    private function ArchiveCheck(int $ac, int $var): bool
    {
        $state = @AC_GetLoggingStatus($ac, $var);
        if ($state) {
            $values = AC_GetLoggedValues($ac, $var, 0, 0, 2);
            if (count($values) === 1 && $values[0]['Value'] == 0) {
                AC_DeleteVariableData($ac, $var, $values[0]['TimeStamp'], 0);
                $this->LogDebug(__FUNCTION__, 'Null values (#' . $var . ') - removed!');
            }
        }
        return (bool) $state;
    }

    /**
     * Checks whether logging is enabled for all archived status variables.
     *
     * @param int $aid Archive Control instance ID.
     *
     * @return bool True if all variables are logged.
     */
    private function IsArchiveLogging(int $aid): bool
    {
        foreach (array_keys(self::ARCHIVE_VARIABLES) as $ident) {
            if (!@AC_GetLoggingStatus($aid, $this->GetIDForIdent($ident))) {
                return false;
            }
        }
        return true;
    }

    /**
     * Checks whether logging and aggregation type are set correctly for all archived status variables.
     *
     * @return bool True if the archive is configured correctly.
     */
    private function IsArchiveConfigured(): bool
    {
        $aid = $this->GetArchiveID();
        if ($aid === 0 || !$this->IsArchiveLogging($aid)) {
            return false;
        }
        foreach (self::ARCHIVE_VARIABLES as $ident => $type) {
            if (AC_GetAggregationType($aid, $this->GetIDForIdent($ident)) !== $type) {
                return false;
            }
        }
        return true;
    }

    /**
     * Returns the status text of the archive configuration for the form.
     *
     * @param bool $archived Result of IsArchiveConfigured().
     *
     * @return string Translated status text.
     */
    private function GetArchiveStatusCaption(bool $archived): string
    {
        return $archived
            ? '✅ ' . $this->Translate('Archiving is enabled for all status variables.')
            : $this->Translate('Archiving is required to save refuelling entries. The button enables it for all status variables with the correct aggregation type.');
    }

    /**
     * Sets the instance status depending on the archive configuration.
     * Without archiving the module can't store anything, so the instance stays inactive.
     *
     * @return bool True if the archive is configured correctly.
     */
    private function UpdateArchiveStatus(): bool
    {
        $archived = $this->IsArchiveConfigured();
        $this->SetStatus($archived ? IS_ACTIVE : self::STATUS_NO_ARCHIVE);
        return $archived;
    }

    /**
     * Show message via popup
     *
     * @param string $caption echo message
     *
     * @return void
     */
    private function EchoMessage(string $caption): void
    {
        $this->UpdateFormField('EchoMessage', 'caption', $this->Translate($caption));
        $this->UpdateFormField('EchoPopup', 'visible', true);
    }

    /**
     * Get the (first) Archive Control instance ID.
     *
     * @return int Archive Control instance ID, or 0.
     */
    private function GetArchiveID(): int
    {
        $ilm = IPS_GetInstanceListByModuleID(self::ARCHIVE_GUID);
        return $ilm[0] ?? 0;
    }

    /**
     * Returns the value of an own status variable, or null if it does not exist (feature disabled).
     *
     * @param string $ident Ident of the status variable.
     *
     * @return mixed Value or null.
     */
    private function GetValueOrNull(string $ident): mixed
    {
        $vid = @IPS_GetObjectIDByIdent($ident, $this->InstanceID);
        return $vid !== false ? GetValue($vid) : null;
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
     * Number of calendar days from today until the given due date (0 = due today, negative = overdue).
     *
     * @param int $due Timestamp of the due date.
     *
     * @return int Days left.
     */
    private function GetDaysLeft(int $due): int
    {
        // round() compensates the 23/25 hour days at DST changes
        return (int) round((strtotime('today', $due) - strtotime('today')) / 86400);
    }

    /**
     * Badge status and text for a date based due date.
     *
     * @param int $daysLeft Result of GetDaysLeft().
     * @param int $reminder Reminder days before due date.
     *
     * @return array{status:string,text:string} Badge status and text.
     */
    private function GetDateBadge(int $daysLeft, int $reminder): array
    {
        $status = $daysLeft <= 0 ? 'critical' : ($daysLeft <= $reminder ? 'warning' : 'normal');
        if ($daysLeft > 0) {
            $text = sprintf($this->Translate('%d days'), $daysLeft);
        } elseif ($daysLeft === 0) {
            $text = $this->Translate('due today');
        } else {
            $text = $this->Translate('overdue');
        }
        return ['status' => $status, 'text' => $text];
    }

    /**
     * Computes the TÜV/Service badge status (normal/warning/critical), using the configured
     * badge colors. Returns null for a badge if it is disabled in the configuration.
     *
     * @param int $mileage Last known mileage (from the archive).
     *
     * @return array<mixed> Badge data for the HTML tile.
     */
    private function GetServiceBadges(int $mileage): array
    {
        $colors = [
            'normal'   => $this->ReadPropertyInteger('ColorNormal'),
            'warning'  => $this->ReadPropertyInteger('ColorWarning'),
            'critical' => $this->ReadPropertyInteger('ColorCritical'),
        ];

        $tuevDate = $this->GetValueOrNull('tuev_due_date');
        $serviceDate = $this->GetValueOrNull('service_due_date');
        $serviceMileage = $this->GetValueOrNull('service_due_mileage');

        $badges = [
            'colors'  => $colors,
            'tuev'    => null,
            'service' => null,
            // Raw values + which fields are actually in use, so the tile can offer manual
            // correction (e.g. TÜV appointment missed or done later than suggested).
            'edit' => [
                'enableTuev'           => $this->ReadPropertyBoolean('EnableTuev'),
                'tuevDate'             => $tuevDate,
                'enableServiceDate'    => $this->ReadPropertyBoolean('EnableService') && $this->ReadPropertyBoolean('ServiceByDate'),
                'serviceDate'          => $serviceDate,
                'enableServiceMileage' => $this->ReadPropertyBoolean('EnableService') && $this->ReadPropertyBoolean('ServiceByMileage'),
                'serviceMileage'       => $serviceMileage,
            ],
        ];

        // TÜV - date based only
        if ($this->ReadPropertyBoolean('EnableTuev') && $tuevDate > 0) {
            $badge = $this->GetDateBadge($this->GetDaysLeft($tuevDate), $this->ReadPropertyInteger('TuevReminderDays'));
            $badges['tuev'] = [
                'status' => $badge['status'],
                'label'  => 'TÜV',
                'text'   => $badge['text'],
            ];
        }

        // Service - by mileage and/or date, whichever is closer/more urgent wins the badge
        if ($this->ReadPropertyBoolean('EnableService')) {
            $candidates = [];

            if ($this->ReadPropertyBoolean('ServiceByDate') && $serviceDate > 0) {
                $daysLeft = $this->GetDaysLeft($serviceDate);
                $badge = $this->GetDateBadge($daysLeft, $this->ReadPropertyInteger('ServiceReminderDays'));
                $candidates[] = [
                    'status' => $badge['status'],
                    'rank'   => $daysLeft,
                    'text'   => $badge['text'],
                ];
            }
            if ($this->ReadPropertyBoolean('ServiceByMileage') && $serviceMileage > 0) {
                $kmLeft = $serviceMileage - $mileage;
                $reminder = $this->ReadPropertyInteger('ServiceReminderKm');
                $status = $kmLeft <= 0 ? 'critical' : ($kmLeft <= $reminder ? 'warning' : 'normal');
                $candidates[] = [
                    'status' => $status,
                    'rank'   => $kmLeft,
                    'text'   => $kmLeft >= 0 ? $kmLeft . ' km' : $this->Translate('overdue'),
                ];
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
            $this->LogMessage('Vehicle image #' . $mediaId . ' not found!', KL_WARNING);
            return '';
        }
        $ext = strtolower(pathinfo($media['MediaFile'], PATHINFO_EXTENSION));
        $mime = match ($ext) {
            'png'   => 'image/png',
            'gif'   => 'image/gif',
            'webp'  => 'image/webp',
            default => 'image/jpeg',
        };
        // IPS_GetMediaContent returns the content base64 encoded
        $content = @IPS_GetMediaContent($mediaId);
        if ($content === false || $content === '') {
            $this->LogMessage('Vehicle image #' . $mediaId . ' has no content!', KL_WARNING);
            return '';
        }
        return 'data:' . $mime . ';base64,' . $content;
    }

    /**
     * Loads all logged values of a variable since $from, plus the last value before it
     * (the archive may skip unchanged values, so an entry can refer to an older value).
     *
     * @param int $aid Archive Control instance ID.
     * @param int $varId Variable ID.
     * @param int $from Start timestamp.
     *
     * @return array<int,float> Values keyed by timestamp, ascending.
     */
    private function GetLoggedSeries(int $aid, int $varId, int $from): array
    {
        $rows = array_merge(
            AC_GetLoggedValues($aid, $varId, 0, $from - 1, 1),
            AC_GetLoggedValues($aid, $varId, $from, 0, 0)
        );
        $series = [];
        foreach ($rows as $row) {
            $series[(int) $row['TimeStamp']] = (float) $row['Value'];
        }
        ksort($series);
        return $series;
    }

    /**
     * Returns the last value of a series logged at or shortly after the given timestamp.
     *
     * @param array<int,float> $series Result of GetLoggedSeries().
     * @param int $ts Timestamp of the refuelling entry.
     *
     * @return float The value, or 0 if none found.
     */
    private function GetSeriesValueAt(array $series, int $ts): float
    {
        $value = 0.0;
        foreach ($series as $time => $val) {
            if ($time > $ts + self::HISTORY_TOLERANCE) {
                break;
            }
            $value = $val;
        }
        return $value;
    }

    /**
     * Assembles the last $limit refuelling entries (correlated across the 5 archived
     * variables by timestamp) for display in the tile's history list.
     *
     * @param int $aid Archive Control instance ID.
     * @param int $limit Maximum number of entries.
     *
     * @return array<int,array{timestamp:int,mileage:int,liters:float,price:float,average:float,costs:float}> List of entries, most recent first.
     */
    private function GetHistory(int $aid, int $limit): array
    {
        // A mileage of 0 is never a valid refuelling (OnSaveInput and the import reject it), only
        // the value written by the archive when the logging was enabled
        $rows = array_values(array_filter(
            AC_GetLoggedValues($aid, $this->GetIDForIdent('kilometers'), 0, 0, $limit),
            fn ($row) => $row['Value'] > 0
        ));
        if (empty($rows)) {
            return [];
        }

        // rows are sorted newest first
        $oldest = (int) $rows[count($rows) - 1]['TimeStamp'];
        $series = [];
        foreach (['liters', 'price', 'average', 'costs'] as $ident) {
            $series[$ident] = $this->GetLoggedSeries($aid, $this->GetIDForIdent($ident), $oldest);
        }

        $history = [];
        foreach ($rows as $row) {
            $ts = (int) $row['TimeStamp'];
            $history[] = [
                'timestamp' => $ts,
                'mileage'   => (int) $row['Value'],
                'liters'    => $this->GetSeriesValueAt($series['liters'], $ts),
                'price'     => $this->GetSeriesValueAt($series['price'], $ts),
                'average'   => $this->GetSeriesValueAt($series['average'], $ts),
                'costs'     => $this->GetSeriesValueAt($series['costs'], $ts),
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
        // AC_GetLoggedValues() raises a warning for variables without logging (e.g. right after creation)
        $history = [];
        $aid = $this->GetArchiveID();
        if ($aid !== 0 && $this->IsArchiveLogging($aid)) {
            $history = $this->GetHistory($aid, self::HISTORY_LIMIT);
        }

        $result = [
            'vehicleImage'   => $this->GetVehicleImageDataUri(),
            'licensePlate'   => $this->ReadPropertyString('LicensePlate'),
            'brandModel'     => $this->ReadPropertyString('BrandModel'),
            'showBrandModel' => $this->ReadPropertyBoolean('ShowBrandModel'),
            'badges'         => $this->GetServiceBadges($history[0]['mileage'] ?? 0),
            'history'        => $history,
        ];

        $this->LogDebug(__FUNCTION__, json_encode($result));

        // send it
        return json_encode($result);
    }
}
