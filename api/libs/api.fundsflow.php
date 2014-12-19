<?php

class FundsFlow {

    protected $alterConf = array();
    protected $billingConf = array();

    public function __construct() {
        $this->loadConfigs();
    }

    /**
     * Preloads system configs
     * 
     * @return void
     */
    protected function loadConfigs() {
        global $ubillingConfig;
        $this->alterConf = $ubillingConfig->getAlter();
        $this->billingConf = $ubillingConfig->getBilling();
    }

    /**
     * Returns array of fees by some login with parsing it from stargazer log
     * 
     * @param string $login existing user login
     * @return array
     */
    public function getFees($login) {
        $login = mysql_real_escape_string($login);

        $sudo = $this->billingConf['SUDO'];
        $cat = $this->billingConf['CAT'];
        $grep = $this->billingConf['GREP'];
        $stglog = $this->alterConf['STG_LOG_PATH'];

        $result = array();

        $feeadmin = 'stargazer';
        $feenote = '';
        $feecashtype = 'z';
        // monthly fees output
        $command = $sudo . ' ' . $cat . ' ' . $stglog . ' | ' . $grep . ' "fee charge"' . ' | ' . $grep . ' "User \'' . $login . '\'" ';
        $rawdata = shell_exec($command);

        if (!empty($rawdata)) {
            $cleardata = exploderows($rawdata);
            foreach ($cleardata as $eachline) {
                $eachfee = explode(' ', $eachline);
                if (isset($eachfee[1])) {
                    $counter = strtotime($eachfee[0] . ' ' . $eachfee[1]);

                    $feefrom = str_replace("'.", '', $eachfee[12]);
                    $feeto = str_replace("'.", '', $eachfee[14]);
                    $feefrom = str_replace("'", '', $feefrom);
                    $feeto = str_replace("'", '', $feeto);

                    $result[$counter]['login'] = $login;
                    $result[$counter]['date'] = $eachfee[0] . ' ' . $eachfee[1];
                    $result[$counter]['admin'] = $feeadmin;
                    $result[$counter]['summ'] = $feeto - $feefrom;
                    $result[$counter]['from'] = $feefrom;
                    $result[$counter]['to'] = $feeto;
                    $result[$counter]['operation'] = 'Fee';
                    $result[$counter]['note'] = $feenote;
                    $result[$counter]['cashtype'] = $feecashtype;
                }
            }
        }
        return ($result);
    }

    /**
     * Returns array of all payments by some user 
     * 
     * @param string $login existing user login
     * @return array
     */
    public function getPayments($login) {
        $login = mysql_real_escape_string($login);
        $query = "SELECT * from `payments` WHERE `login`='" . $login . "'";
        $allpayments = simple_queryall($query);

        $result = array();

        if (!empty($allpayments)) {
            foreach ($allpayments as $io => $eachpayment) {
                $counter = strtotime($eachpayment['date']);

                if (ispos($eachpayment['note'], 'MOCK:')) {
                    $cashto = $eachpayment['balance'];
                }

                if (ispos($eachpayment['note'], 'BALANCESET:')) {
                    $cashto = $eachpayment['summ'];
                }

                if ((!ispos($eachpayment['note'], 'MOCK:')) AND ( !ispos($eachpayment['note'], 'BALANCESET:'))) {
                    $cashto = $eachpayment['summ'] + $eachpayment['balance'];
                }

                $result[$counter]['login'] = $login;
                $result[$counter]['date'] = $eachpayment['date'];
                $result[$counter]['admin'] = $eachpayment['admin'];
                $result[$counter]['summ'] = $eachpayment['summ'];
                $result[$counter]['from'] = $eachpayment['balance'];
                $result[$counter]['to'] = $cashto;
                $result[$counter]['operation'] = 'Payment';
                $result[$counter]['note'] = $eachpayment['note'];
                $result[$counter]['cashtype'] = $eachpayment['cashtypeid'];
            }
        }

        return ($result);
    }

    /**
     * Returns array of all payments of user by some login
     *  
     * @param string $login existing user login
     * @return array
     */
    public function getPaymentsCorr($login) {
        $login = mysql_real_escape_string($login);
        $query = "SELECT * from `paymentscorr` WHERE `login`='" . $login . "'";
        $allpayments = simple_queryall($query);

        $result = array();


        if (!empty($allpayments)) {
            foreach ($allpayments as $io => $eachpayment) {
                $counter = strtotime($eachpayment['date']);
                $cashto = $eachpayment['summ'] + $eachpayment['balance'];
                $result[$counter]['login'] = $login;
                $result[$counter]['date'] = $eachpayment['date'];
                $result[$counter]['admin'] = $eachpayment['admin'];
                $result[$counter]['summ'] = $eachpayment['summ'];
                $result[$counter]['from'] = $eachpayment['balance'];
                $result[$counter]['to'] = $cashto;
                $result[$counter]['operation'] = 'Correcting';
                $result[$counter]['note'] = $eachpayment['note'];
                $result[$counter]['cashtype'] = $eachpayment['cashtypeid'];
            }
        }

        return ($result);
    }

    /**
     * Returns array of cashtype names
     * 
     * @return array
     */
    function getCashTypeNames() {
        $query = "SELECT * from `cashtype`";
        $alltypes = simple_queryall($query);
        $result = array();

        if (!empty($alltypes)) {
            foreach ($alltypes as $io => $each) {
                $result[$each['id']] = __($each['cashtype']);
            }
        }

        return ($result);
    }

    /**
     * Renders result of default fundsflow module
     * 
     * @param array $fundsflow
     */
    public function renderArray($fundsflow) {
        $allcashtypes = $this->getCashTypeNames();
        $allservicenames = zb_VservicesGetAllNamesLabeled();
        $result = '';

        $tablecells = wf_TableCell(__('Date'));
        $tablecells.=wf_TableCell(__('Cash'));
        $tablecells.=wf_TableCell(__('From'));
        $tablecells.=wf_TableCell(__('To'));
        $tablecells.=wf_TableCell(__('Operation'));
        $tablecells.=wf_TableCell(__('Cash type'));
        $tablecells.=wf_TableCell(__('Notes'));
        $tablecells.=wf_TableCell(__('Admin'));
        $tablerows = wf_TableRow($tablecells, 'row1');

        if (!empty($fundsflow)) {
            foreach ($fundsflow as $io => $each) {
                //cashtype
                if ($each['cashtype'] != 'z') {
                    @$cashtype = $allcashtypes[$each['cashtype']];
                } else {
                    $cashtype = __('Fee');
                }

                //coloring
                $efc = wf_tag('font', true);

                if ($each['operation'] == 'Fee') {
                    $fc = wf_tag('font', false, '', 'color="#a90000"');
                }

                if ($each['operation'] == 'Payment') {
                    $fc = wf_tag('font', false, '', 'color="#005304"');
                }

                if ($each['operation'] == 'Correcting') {
                    $fc = wf_tag('font', false, '', 'color="#ff6600"');
                }

                if (ispos($each['note'], 'MOCK:')) {
                    $fc = wf_tag('font', false, '', 'color="#006699"');
                }

                if (ispos($each['note'], 'BALANCESET:')) {
                    $fc = wf_tag('font', false, '', 'color="##000000"');
                }


                //notes translation
                if ($this->alterConf['TRANSLATE_PAYMENTS_NOTES']) {
                    $displaynote = zb_TranslatePaymentNote($each['note'], $allservicenames);
                } else {
                    $displaynote = $each['note'];
                }

                $tablecells = wf_TableCell($fc . $each['date'] . $efc, '150');
                $tablecells.=wf_TableCell($fc . $each['summ'] . $efc);
                $tablecells.=wf_TableCell($fc . $each['from'] . $efc);
                $tablecells.=wf_TableCell($fc . $each['to'] . $efc);
                $tablecells.=wf_TableCell($fc . __($each['operation']) . $efc);
                $tablecells.=wf_TableCell($cashtype);
                $tablecells.=wf_TableCell($displaynote);
                $tablecells.=wf_TableCell($each['admin']);
                $tablerows.= wf_TableRow($tablecells, 'row3');
            }

            $legendcells = wf_TableCell(__('Legend') . ':');
            $legendcells.= wf_TableCell(wf_tag('font', false, '', 'color="#005304"') . __('Payment') . $efc);
            $legendcells.= wf_TableCell(wf_tag('font', false, '', 'color="#a90000"') . __('Fee') . $efc);
            $legendcells.= wf_TableCell(wf_tag('font', false, '', 'color="#ff6600"') . __('Correct saldo') . $efc);
            $legendcells.= wf_TableCell(wf_tag('font', false, '', 'color="#006699"') . __('Mock payment') . $efc);
            $legendcells.= wf_TableCell(wf_tag('font', false, '', 'color="##000000"') . __('Set cash') . $efc);
            $legendrows = wf_TableRow($legendcells, 'row3');

            $legend = wf_TableBody($legendrows, '50%', 0, 'glamour');
            $legend.=wf_tag('div', false, '', 'style="clear:both;"') . wf_tag('div', true);
            $legend.=wf_delimiter();

            $result = wf_TableBody($tablerows, '100%', 0, 'sortable');
            $result.=$legend;
        }

        return ($result);
    }

    /**
     *  transforms array for normal output
     * 
     * @param array $fundsflow
     * @return array
     */
    public function transformArray($fundsflow) {
        if (!empty($fundsflow)) {
            ksort($fundsflow);
            $fundsflow = array_reverse($fundsflow);
        }
        return ($fundsflow);
    }
    
    
    /**
     * Extracts funds only with some date pattern
     * 
     * @param array  $fundsflow standard fundsflow array
     * @param string $date
     * @return array
     */
    public function filterByDate($fundsflow,$date) {
        $result=array();
        if (!empty($fundsflow)) {
            foreach ($fundsflow as $timestamp=>$flowdata) {
                if (ispos($flowdata['date'], $date)) {
                    $result[$timestamp]=$flowdata;
                }
            }
        }
    
        return ($result);
    }

}

?>