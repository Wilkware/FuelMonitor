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

        ///////////////////////////////////////////////////////////////////////
        // Profile Time
        $time = [
            [0, 'Today', '', 0xFFFF00],
            [1, 'Yesterday', '', 0xFFFF00],
            [2, 'Day before yesterday', '', 0xFFFF00],
        ];
        $this->RegisterProfile(vtInteger, 'SVM.Time', 'Clock', '', '', 0, 0, 0, 0, $time);
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
        $this->RegisterProfile(vtInteger, 'SVM.Mileage', 'Speedo', '', '', 0, 0, 0, 0, $mileage);
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
        $this->RegisterProfile(vtFloat, 'SVM.TankCapacity', 'Gauge', '', '', 0, 0, 0, 2, $capacity);
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
        $this->RegisterProfile(vtFloat, 'SVM.LitrePrice', 'Euro', '', '', 0, 0, 0, 3, $price);
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
        $this->RegisterProfile(vtFloat, 'SVM.TankBill', 'Euro', '', '', 0, 0, 0, 2, $bill);
        // Profile RefuelTyp
        $refuel = [
            [0, 'Initial filling', '', 0x0080FF],
            [1, 'Partial refuelling', '', 0xFFFF80],
            [2, 'Full refuelling', '', 0x80FF80],
        ];
        $this->RegisterProfile(vtInteger, 'SVM.RefuelTyp', 'Tap', '', '', 0, 0, 0, 0, $refuel);
        // Profile SaveInput
        $save = [
            [0, '►', '', 0xFF8000],
        ];
        $this->RegisterProfile(vtInteger, 'SVM.SaveInput', 'Script', '', '', 0, 0, 0, 0, $save);
        // Profile Kilometers, Liters & Price
        $this->RegisterProfile(vtInteger, 'SVM.Kilometers', 'Distance', '', ' km', 0, 0, 0, 2);
        $this->RegisterProfile(vtFloat, 'SVM.Liters', 'Tap', '', ' l', 0, 0, 0, 2);
        $this->RegisterProfile(vtFloat, 'SVM.Price', 'Euro', '', ' €', 0, 0, 0, 3);
        $this->RegisterProfile(vtFloat, 'SVM.Average', 'Graph', '', ' l/100km', 0, 0, 0, 2);
        $this->RegisterProfile(vtFloat, 'SVM.Costs', 'Graph', '', ' €/100km', 0, 0, 0, 2);
        ///////////////////////////////////////////////////////////////////////
        // Archive ID
        $ilm = IPS_GetInstanceListByModuleID(self::ARCHIVE_GUID);
        $aid = $ilm[0];
        // Register status variables + statistics
        $vid = $this->RegisterVariableInteger('kilometers', $this->Translate('Kilometers'), 'SVM.Kilometers', 0);
        AC_SetLoggingStatus($aid, $vid, true);
        AC_SetAggregationType($aid, $vid, self::ARCHIVE_COUNTER);
        AC_SetCounterIgnoreZeros($aid, $vid, true);
        $vid = $this->RegisterVariableFloat('liters', $this->Translate('Liters'), 'SVM.Liters', 1);
        AC_SetLoggingStatus($aid, $vid, true);
        AC_SetAggregationType($aid, $vid, self::ARCHIVE_COUNTER);
        AC_SetCounterIgnoreZeros($aid, $vid, true);
        $vid = $this->RegisterVariableFloat('price', $this->Translate('Price'), 'SVM.Price', 2);
        AC_SetLoggingStatus($aid, $vid, true);
        AC_SetAggregationType($aid, $vid, self::ARCHIVE_DEFAULT);
        $vid = $this->RegisterVariableFloat('average', $this->Translate('Average fuel consumption'), 'SVM.Average', 3);
        AC_SetLoggingStatus($aid, $vid, true);
        AC_SetAggregationType($aid, $vid, self::ARCHIVE_DEFAULT);
        $vid = $this->RegisterVariableFloat('costs', $this->Translate('Costs'), 'SVM.Costs', 4);
        AC_SetLoggingStatus($aid, $vid, true);
        AC_SetAggregationType($aid, $vid, self::ARCHIVE_DEFAULT);
        ///////////////////////////////////////////////////////////////////////
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
        // Get Form
        $form = json_decode(file_get_contents(__DIR__ . '/form.json'), true);
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
                $this->SetValueInteger($ident, $value);
                break;
            case 'mileage':
                $calc = $this->OnCalcValue($ident, $value, self::MIN_MILEAGE, self::MAX_MILEAGE);
                $this->SetValueInteger($ident, $calc);
                break;
            case 'quantity':
                $calc = $this->OnCalcValue($ident, $value, self::MIN_CAPACITY, self::MAX_CAPACITY);
                $this->SetValueFloat($ident, $calc);
                break;
            case 'price_per_litre':
                $calc = $this->OnCalcValue($ident, $value, self::MIN_PRICE, self::MAX_PRICE);
                $this->SetValueFloat($ident, $calc);
                break;
            case 'invoice':
                $calc = $this->OnCalcValue($ident, $value, self::MIN_INVOICE, self::MAX_INVOICE);
                $this->SetValueFloat($ident, $calc);
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

    // Berechnet entsprechend der Auswahl den neuen Wert
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
        // get statistics
        $ks = $this->GetIDForIdent('kilometers');
        $ls = $this->GetIDForIdent('liters');
        $ps = $this->GetIDForIdent('price');
        $as = $this->GetIDForIdent('average');
        $cs = $this->GetIDForIdent('costs');
        // calculate

        // save
        $ilm = IPS_GetInstanceListByModuleID(self::ARCHIVE_GUID);
        $aid = $ilm[0];
        $vid = $this->GetIDForIdent('kilometers');
        AC_AddLoggedValues($aid, $vid, [['TimeStamp' => $ts, 'Value' => $mi]]);
        $vid = $this->GetIDForIdent('liters');
        AC_AddLoggedValues($aid, $vid, [['TimeStamp' => $ts, 'Value' => $tq]]);
        $vid = $this->GetIDForIdent('price');
        AC_AddLoggedValues($aid, $vid, [['TimeStamp' => $ts, 'Value' => $pl]]);
    }
}
