<?php

declare(strict_types=1);

// Generell funktions
require_once __DIR__ . '/../libs/_traits.php';

// CLASS Fuel Monitor
class FuelMonitor extends IPSModule
{
    use ProfileHelper;
    use DebugHelper;
    use VariableHelper;

    // Archive GUID
    private const ARCHIVE_GUID = '{43192F0B-135B-4CE7-A0A7-1475603F3060}';
    private const ARCHIVE_DEFAULT = 0;
    private const ARCHIVE_COUNTER = 1;

    // Min/Max constant
    private const MIN_MILEAGE = 0;        // 0 km
    private const MAX_MILEAGE = 1000000;  // 1.000.0000 km
    private const MIN_CAPACITY = 0;        // 0 Liter
    private const MAX_CAPACITY = 1000;     // 100 Liter
    private const MIN_PRICE = 0;        // 0,000 Euro
    private const MAX_PRICE = 10;       // 10,000 Euro
    private const MIN_INVOICE = 0;        // 0,00 Euro
    private const MAX_INVOICE = 10000;    // 10.000,00 Euro

    /**
     * Create.
     */
    public function Create()
    {
        //Never delete this line!
        parent::Create();

        // Profile Time
        $time = [
            [0, 'Today', '', 0xFFFF00],
            [1, 'Yesterday', '', 0xFFFF00],
            [2, 'Day before yesterday', '', 0xFFFF00],
        ];
        $this->RegisterProfileInteger('SVM.Time', 'Clock', '', '', 0, 0, 0, $time);
        // Profile Mileage
        $mileage = [
            [-100, '-100', '', -1],
            [-10, '-10', '', -1],
            [-1, '-1', '', -1],
            [0, '%d km', '', 0x00FF00],
            [1000001, '+1', '', -1],
            [1000010, '+10', '', -1],
            [1000100, '+100', '', -1],
        ];
        $this->RegisterProfileInteger('SVM.Mileage', 'Speedo', '', '', 0, 0, 0, $mileage);
        // Profile TankCapacity
        $capacity = [
            [-10, '-10,00', '', -1],
            [-1, '-1,00', '', -1],
            [-0.1, '-0,10', '', -1],
            [-0.01, '-0,01', '', -1],
            [0, '%0.2f Liter', '', 0x0000FF],
            [1000.01, '+0,01', '', -1],
            [1000.1, '+0,10', '', -1],
            [1001, '+1,00', '', -1],
            [1010, '+10,00', '', -1],
        ];
        $this->RegisterProfileFloat('SVM.TankCapacity', 'Gauge', '', '', 0, 0, 0, 2, $capacity);
        // Profile LitrePrice
        $price = [
            [-0.1, '-0,1', '', -1],
            [-0.01, '-0,01', '', -1],
            [-0.001, '-0,001', '', -1],
            [0, '%0.3f €', '', 0x808080],
            [10.001, '+0,001', '', -1],
            [10.01, '+0,01', '', -1],
            [10.1, '+0,1', '', -1],
        ];
        $this->RegisterProfileFloat('SVM.LitrePrice', 'Euro', '', '', 0, 0, 0, 3, $price);
        // Profile TankBill
        $bill = [
            [-10, '-10,00', '', -1],
            [-1, '-1,00', '', -1],
            [-0.1, '-0,10', '', -1],
            [-0.01, '-0,01', '', -1],
            [0, '%0.2f €', '', 0x8000FF],
            [10000.01, '+0,01', '', -1],
            [10000.1, '+0,10', '', -1],
            [10001, '+1,00', '', -1],
            [10010, '+10,00', '', -1],
        ];
        $this->RegisterProfileFloat('SVM.TankBill', 'Euro', '', '', 0, 0, 0, 2, $bill);
        // Profile RefuelTyp
        $refuel = [
            [0, 'Initial filling', '', 0x0080FF],
            [1, 'Partial refuelling', '', 0xFFFF80],
            [2, 'Full refuelling', '', 0x80FF80],
        ];
        $this->RegisterProfileInteger('SVM.RefuelTyp', 'Tap', '', '', 0, 0, 0, $refuel);
        // Profile SaveInput
        $save = [
            [0, '►', '', 0xFF8000],
        ];
        $this->RegisterProfileInteger('SVM.SaveInput', 'Script', '', '', 0, 0, 0, $save);
        // Profile Kilometers, Liters & Price
        $this->RegisterProfileInteger('SVM.Kilometers', 'Distance', '', ' km', 0, 0, 2);
        $this->RegisterProfileFloat('SVM.Liters', 'Tap', '', ' l', 0, 0, 0, 2);
        $this->RegisterProfileFloat('SVM.Price', 'Euro', '', ' €', 0, 0, 0, 3);
        $this->RegisterProfileFloat('SVM.Average', 'Graph', '', ' l/100km', 0, 0, 0, 2);
        $this->RegisterProfileFloat('SVM.Costs', 'Graph', '', ' €/100km', 0, 0, 0, 2);

        // Register property variables
        // Calculation ...
        $this->RegisterPropertyBoolean('CalcQuantity', false);
        $this->RegisterPropertyBoolean('QuantityByPrice', false);
        $this->RegisterPropertyBoolean('QuantityByInvoice', false);
        $this->RegisterPropertyBoolean('CalcPrice', false);
        $this->RegisterPropertyBoolean('PriceByQuantity', false);
        $this->RegisterPropertyBoolean('PriceByInvoice', false);
        $this->RegisterPropertyBoolean('CalcInvoice', false);
        $this->RegisterPropertyBoolean('InvoiceByQuantity', false);
        $this->RegisterPropertyBoolean('InvoiceByPrice', false);
        // Advanced ...
        $this->RegisterPropertyBoolean('FutureDate', true);
        $this->RegisterPropertyBoolean('MileageDecreases', true);
        $this->RegisterPropertyBoolean('FirstInitial', true);

        // Archive ID
        $ilm = IPS_GetInstanceListByModuleID(self::ARCHIVE_GUID);
        $aid = $ilm[0];
        // Register status variables + statistics
        $vid = $this->RegisterVariableInteger('kilometers', $this->Translate('Kilometers'), 'SVM.Kilometers', 0);
        $this->ArchiveVariable($aid, $vid, true, self::ARCHIVE_COUNTER, true);
        $vid = $this->RegisterVariableFloat('liters', $this->Translate('Liters'), 'SVM.Liters', 1);
        $this->ArchiveVariable($aid, $vid, true, self::ARCHIVE_COUNTER, true);
        $vid = $this->RegisterVariableFloat('price', $this->Translate('Price'), 'SVM.Price', 2);
        $this->ArchiveVariable($aid, $vid, true, self::ARCHIVE_DEFAULT, false);
        $vid = $this->RegisterVariableFloat('average', $this->Translate('Average fuel consumption'), 'SVM.Average', 3);
        $this->ArchiveVariable($aid, $vid, true, self::ARCHIVE_DEFAULT, false);
        $vid = $this->RegisterVariableFloat('costs', $this->Translate('Costs'), 'SVM.Costs', 4);
        $this->ArchiveVariable($aid, $vid, true, self::ARCHIVE_DEFAULT, false);

        // Register status variables + actions
        $this->RegisterVariableInteger('time', $this->Translate('Time'), 'SVM.Time', 10);
        $this->EnableAction('time');
        $this->RegisterVariableInteger('date', $this->Translate('Date'), '~UnixTimestampDate', 11);
        $this->SetValueInteger('date', time());
        $this->EnableAction('date');
        $this->RegisterVariableInteger('mileage', $this->Translate('Mileage'), 'SVM.Mileage', 12);
        // $this->SetValueInteger('mileage', 1000);
        $this->EnableAction('mileage');
        $this->RegisterVariableFloat('quantity', $this->Translate('Quantity'), 'SVM.TankCapacity', 13);
        //$this->SetValueFloat('quantity', 50.00);
        $this->EnableAction('quantity');
        $this->RegisterVariableFloat('price_per_litre', $this->Translate('Price per litre'), 'SVM.LitrePrice', 14);
        //$this->SetValueFloat('price_per_litre', 1.999);
        $this->EnableAction('price_per_litre');
        $this->RegisterVariableFloat('invoice', $this->Translate('Invoice'), 'SVM.TankBill', 15);
        //$this->SetValueFloat('invoice', 99.95);
        $this->EnableAction('invoice');
        $this->RegisterVariableInteger('refuelling', $this->Translate('Refuelling'), 'SVM.RefuelTyp', 16);
        //$this->SetValueInteger('refuelling', 0);
        $this->EnableAction('refuelling');
        $this->RegisterVariableInteger('save_input', $this->Translate('Save'), 'SVM.SaveInput', 17);
        //$this->SetValueInteger('save_input', 0);
        $this->EnableAction('save_input');

        // Register attributes
        $this->RegisterAttributeString('History', '[]');
    }

    /**
     * Destroy.
     */
    public function Destroy()
    {
        parent::Destroy();
    }

    /**
     * Configuration Form.
     *
     * @return JSON configuration string.
     */
    public function GetConfigurationForm()
    {
        // Read Setup
        $quantity = $this->ReadPropertyBoolean('CalcQuantity');
        $price = $this->ReadPropertyBoolean('CalcPrice');
        $invoice = $this->ReadPropertyBoolean('CalcInvoice');
        // Get Form
        $form = json_decode(file_get_contents(__DIR__ . '/form.json'), true);
        // Enable or disable
        $form['elements'][2]['items'][0]['items'][1]['enabled'] = $quantity;
        $form['elements'][2]['items'][0]['items'][2]['enabled'] = $quantity;
        $form['elements'][2]['items'][1]['items'][1]['enabled'] = $price;
        $form['elements'][2]['items'][1]['items'][2]['enabled'] = $price;
        $form['elements'][2]['items'][2]['items'][1]['enabled'] = $invoice;
        $form['elements'][2]['items'][2]['items'][2]['enabled'] = $invoice;
        // return form
        return json_encode($form);
    }

    /**
     * Apply Configuration Changes.
     */
    public function ApplyChanges()
    {
        //Never delete this line!
        parent::ApplyChanges();
        //      $this->SendDebug(__FUNCTION__, 'Debug = ');
    }

    /**
     * RequestAction.
     *
     *  @param string $ident Ident.
     *  @param string $value Value.
     */
    public function RequestAction($ident, $value)
    {
        // Debug output
        $this->SendDebug(__FUNCTION__, $ident . ' => ' . $value);
        switch ($ident) {
            case 'time':
                $this->SetValueInteger($ident, $value);
                $timestamp = strtotime('-' . $value . ' day');
                $this->SetValueInteger('date', $timestamp);
                break;
            case 'date':
                $now = date('Ymd', time());
                $date = date('Ymd', $value);
                // no future time
                if ($date <= $now) {
                    $this->SetValueInteger($ident, $value);
                } else {
                    $future = $this->ReadPropertyBoolean('FutureDate');
                    if ($future) {
                        echo $this->Translate('Date is in the future!');
                    }
                }
                break;
            case 'mileage':
                $calc = $this->OnCalcValue($ident, $value, self::MIN_MILEAGE, self::MAX_MILEAGE);
                $this->SetValueInteger($ident, $calc);
                break;
            case 'quantity':
                $calc = $this->OnCalcValue($ident, $value, self::MIN_CAPACITY, self::MAX_CAPACITY);
                $this->SetValueFloat($ident, $calc);
                $this->ModifyByQuantity($calc);
                break;
            case 'price_per_litre':
                $calc = $this->OnCalcValue($ident, $value, self::MIN_PRICE, self::MAX_PRICE);
                $this->SetValueFloat($ident, $calc);
                $this->ModifyByPrice($calc);
                break;
            case 'invoice':
                $calc = $this->OnCalcValue($ident, $value, self::MIN_INVOICE, self::MAX_INVOICE);
                $this->SetValueFloat($ident, $calc);
                $this->ModifyByInvoice($calc);
                break;
            case 'refuelling':
                $this->SetValueInteger($ident, $value);
                break;
            case 'save_input':
                $this->OnSaveInput($value);
                break;
            default:
                eval('$this->' . $ident . '(\'' . $value . '\');');
        }
        return true;
    }

    /**
     * Modify quantity calculation.
     *
     * @param string $value Selection values.
     */
    protected function OnChangeQuality($value)
    {
        $data = unserialize($value);
        //$this->SendDebug(__FUNCTION__, $data);
        // Enable or disable?
        $enabled = $data['c4q'];
        $this->UpdateFormField('QuantityByPrice', 'enabled', $enabled);
        $this->UpdateFormField('QuantityByInvoice', 'enabled', $enabled);
        // Safty check - only one dependency allowed
        if ($enabled) {
            if ($data['q@p']) {
                $this->UpdateFormField('InvoiceByPrice', 'value', false);
            }
            if ($data['q@i']) {
                $this->UpdateFormField('PriceByInvoice', 'value', false);
            }
        }
    }

    /**
     * Modify price per litre calculation.
     *
     * @param string $value Selection values.
     */
    protected function OnChangePrice($value)
    {
        $data = unserialize($value);
        //$this->SendDebug(__FUNCTION__, $data);
        // Enable or disable?
        $enabled = $data['c4p'];
        $this->UpdateFormField('PriceByQuantity', 'enabled', $enabled);
        $this->UpdateFormField('PriceByInvoice', 'enabled', $enabled);
        // Safty check - only one dependency allowed
        if ($enabled) {
            if ($data['p@q']) {
                $this->UpdateFormField('InvoiceByQuantity', 'value', false);
            }
            if ($data['p@i']) {
                $this->UpdateFormField('QuantityByInvoice', 'value', false);
            }
        }
    }

    /**
     * Modify invoice calculation.
     *
     * @param string $value Selection values.
     */
    protected function OnChangeInvoice($value)
    {
        $data = unserialize($value);
        //$this->SendDebug(__FUNCTION__, $data);
        // Enable or disable?
        $enabled = $data['c4i'];
        $this->UpdateFormField('InvoiceByQuantity', 'enabled', $enabled);
        $this->UpdateFormField('InvoiceByPrice', 'enabled', $enabled);
        // Safty check - only one dependency allowed
        if ($enabled) {
            if ($data['i@q']) {
                $this->UpdateFormField('PriceByQuantity', 'value', false);
            }
            if ($data['i@p']) {
                $this->UpdateFormField('QuantityByPrice', 'value', false);
            }
        }
    }

    /**
     * Calculate depends on selection the new profile value
     *
     * @param string $ident Ident.
     * @param mixed $value Value
     * @param int $min Minimum value.
     * @param int $max Maximum value.
     */
    private function OnCalcValue($ident, $value, $min, $max)
    {
        $current = $this->GetValue($ident);
        // special case (direct click)
        if ($value == $min) {
            return $current;
        }
        // step by step up or down
        if ($value < $min) {
            $value = $current - abs(abs($min) - abs($value));
        }
        if ($value > $max) {
            $value = $current + ($value - $max);
        }
        // prevent overflow
        if ($value < $min) {
            $value = $min;
        }
        if ($value > $max) {
            $value = $max;
        }
        // return value
        return $value;
    }

    /**
     * Modify values depends on setup configuration
     *
     * @param float $value New quantity value.
     */
    private function ModifyByQuantity($value)
    {
        if ($this->ReadPropertyBoolean('CalcPrice')) {
            if ($this->ReadPropertyBoolean('PriceByQuantity')) {
                $invoice = $this->GetValue('invoice');
                $price = round($invoice / $value, 3);
                $this->SetValueFloat('price_per_litre', $price);
                $this->SendDebug(__FUNCTION__, 'price_per_litre => ' . $price);
            }
        }

        if ($this->ReadPropertyBoolean('CalcInvoice')) {
            if ($this->ReadPropertyBoolean('InvoiceByQuantity')) {
                $price = $this->GetValue('price_per_litre');
                $invoice = round($price * $value, 2);
                $this->SetValueFloat('invoice', $invoice);
                $this->SendDebug(__FUNCTION__, 'invoice => ' . $invoice);
            }
        }
    }

    /**
     * Modify values depends on setup configuration
     *
     * @param float $value New proce per litre value.
     */
    private function ModifyByPrice($value)
    {
        if ($this->ReadPropertyBoolean('CalcQuantity')) {
            if ($this->ReadPropertyBoolean('QuantityByPrice')) {
                $invoice = $this->GetValue('invoice');
                $quantity = round($invoice / $value, 2);
                $this->SetValueFloat('quantity', $quantity);
                $this->SendDebug(__FUNCTION__, 'quantity => ' . $quantity);
            }
        }
        if ($this->ReadPropertyBoolean('CalcInvoice')) {
            if ($this->ReadPropertyBoolean('InvoiceByPrice')) {
                $quantity = $this->GetValue('quantity');
                $invoice = round($quantity * $value, 2);
                $this->SetValueFloat('invoice', $invoice);
                $this->SendDebug(__FUNCTION__, 'invoice => ' . $invoice);
            }
        }
    }

    /**
     * Modify values depends on setup configuration
     *
     * @param float $value New invoice value.
     */
    private function ModifyByInvoice($value)
    {
        if ($this->ReadPropertyBoolean('CalcQuantity')) {
            if ($this->ReadPropertyBoolean('QuantityByInvoice')) {
                $price = $this->GetValue('price_per_litre');
                $quantity = round($value / $price, 2);
                $this->SetValueFloat('quantity', $quantity);
                $this->SendDebug(__FUNCTION__, 'quantity => ' . $quantity);
            }
        }
        if ($this->ReadPropertyBoolean('CalcPrice')) {
            if ($this->ReadPropertyBoolean('PriceByInvoice')) {
                $quantity = $this->GetValue('quantity');
                $price = round($value / $quantity, 3);
                $this->SetValueFloat('price_per_litre', $price);
                $this->SendDebug(__FUNCTION__, 'price_per_litre => ' . $price);
            }
        }
    }

    /**
     * User has activate save action.
     *
     * @param int $value save value.
     */
    private function OnSaveInput($value)
    {
        // get data
        $ts = $this->GetValue('date');
        $mi = $this->GetValue('mileage');
        $tq = $this->GetValue('quantity');
        $pl = $this->GetValue('price_per_litre');
        $iv = $this->GetValue('invoice');
        $rt = $this->GetValue('refuelling');
        $this->SendDebug(__FUNCTION__, 'Date: ' . $ts . ',Milage: ' . $mi . ',Quantity: ' . $tq . ',Price: ' . $pl . ',Invoice: ' . $iv . ',Typ: ' . $rt);

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
            return false;
        }

        // check logging status
        $status = true;
        $status = $status && $this->ArchiveCheck($aid, $ks);
        $status = $status && $this->ArchiveCheck($aid, $ls);
        $status = $status && $this->ArchiveCheck($aid, $ps);
        $status = $status && $this->ArchiveCheck($aid, $as);
        $status = $status && $this->ArchiveCheck($aid, $cs);
        if (!$status) {
            $this->LogMessage('Archive Logging Status not valid!', KL_WARNING);
            return false;
        }

        // first save?
        $lastValue = AC_GetLoggedValues($aid, $ks, 0, 0, 1);
        $first = empty($lastValue);
        $this->SendDebug(__FUNCTION__, 'First Save: ' . boolval($first));

        // then also selected?
        if ($first && $rt != 0) {
            echo $this->Translate('Initially please start with a first filling!');
            return false;
        }

        // advanced check
        $decrease = $this->ReadPropertyBoolean('MileageDecreases');
        $initial = $this->ReadPropertyBoolean('FirstInitial');

        if (!$first && $initial && ($rt == 0)) {
            echo $this->Translate('First filling only allowed when saving for the first time!');
            return false;
        }

        $km = $this->GetValue('kilometers');
        if ($km >= $mi) {
            if (($rt > 0) || $initial) {
                if ($decrease) {
                    echo $this->Translate('Mileage is less than or unchanged from the last registration!');
                }
                $this->SendDebug(__FUNCTION__, 'Mileage is less than or unchanged from the last registration!');
                return false;
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
            $this->SendDebug(__FUNCTION__, 'ReInit Archive - delete all old values!');
            // Reset History Attribute
            $this->WriteAttributeString('History', '[]');
        }

        $reaggregate = false;

        // calculate
        if ($rt == 0) {
            // save
            AC_AddLoggedValues($aid, $ks, [['TimeStamp' => $ts, 'Value' => $mi]]); //$this->SetValueInteger(, $mi);
            AC_AddLoggedValues($aid, $ls, [['TimeStamp' => $ts, 'Value' => $tq]]); //$this->SetValueFloat(, $tq);
            AC_AddLoggedValues($aid, $ps, [['TimeStamp' => $ts, 'Value' => $pl]]); //$this->SetValueFloat(, $pl);
            AC_AddLoggedValues($aid, $as, [['TimeStamp' => $ts, 'Value' => 0]]); //$this->SetValueFloat(, 0);
            AC_AddLoggedValues($aid, $cs, [['TimeStamp' => $ts, 'Value' => 0]]); //$this->SetValueFloat(, 0);
            $this->SendDebug(__FUNCTION__, 'Log Values for initial filling!');
            $reaggregate = true;
        } elseif ($rt == 1) {
        } else {
            $lastks = AC_GetLoggedValues($aid, $ks, 0, 0, 1);
            $this->SendDebug(__FUNCTION__, $lastks);
            $lastls = AC_GetLoggedValues($aid, $ls, 0, 0, 1);
            $this->SendDebug(__FUNCTION__, $lastls);
            $distance = $mi - $lastks[0]['Value'];
            $this->SendDebug(__FUNCTION__, 'Distance: ' . $distance);
            $consumption = ($tq * 100) / $distance;
            $this->SendDebug(__FUNCTION__, 'Consumption: ' . $consumption);
            $cost = $pl * $consumption;
            $this->SendDebug(__FUNCTION__, 'Costs: ' . $cost);
            AC_AddLoggedValues($aid, $ks, [['TimeStamp' => $ts, 'Value' => $mi]]); //$this->SetValueInteger(, $mi);
            AC_AddLoggedValues($aid, $ls, [['TimeStamp' => $ts, 'Value' => $tq]]); //$this->SetValueFloat(, $tq);
            AC_AddLoggedValues($aid, $ps, [['TimeStamp' => $ts, 'Value' => $pl]]); //$this->SetValueFloat(, $pl);
            AC_AddLoggedValues($aid, $as, [['TimeStamp' => $ts, 'Value' => $consumption]]); //$this->SetValueFloat(, 0);
            AC_AddLoggedValues($aid, $cs, [['TimeStamp' => $ts, 'Value' => $cost]]); //$this->SetValueFloat(, 0);
            $this->SendDebug(__FUNCTION__, 'Log Values for full filling!');
            $reaggregate = true;
        }

        // AC_ReAggregateVariable
        if ($reaggregate) {
            $status = true;
            $status = $status && AC_ReAggregateVariable($aid, $ks);
            $status = $status && AC_ReAggregateVariable($aid, $ls);
            $status = $status && AC_ReAggregateVariable($aid, $ps);
            $status = $status && AC_ReAggregateVariable($aid, $as);
            $status = $status && AC_ReAggregateVariable($aid, $cs);
            $this->SendDebug(__FUNCTION__, 'Status ReAggregate: ' . boolval($status));
        }
    }

    /**
     * Control Archive Variables
     *
     * @param int $ac Arcive Control ID
     * @param int $var Variable ID
     * @param bool $state Variable archive logging state
     * @param int $type Variable aggregation type
     * @param bool $zero Ignore null values
     */
    private function ArchiveVariable($ac, $var, $state, $type, $zero)
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
     * @return bool True if enabled, otherwise false.
     */
    private function ArchiveCheck($ac, $var)
    {
        $state = @AC_GetLoggingStatus($ac, $var);
        if ($state) {
            $lastValue = AC_GetLoggedValues($ac, $var, 0, 0, 1);
            if (!empty($lastValue) && (count($lastValue) == 1) && ($lastValue[0]['Value'] == 0)) {
                $ret = AC_DeleteVariableData($ac, $var, $lastValue[0]['TimeStamp'], 0);
                $this->SendDebug(__FUNCTION__, 'Null values (#' . $var . ') - removed!');
            }
        }
        return $state;
    }
}
