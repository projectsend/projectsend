<?php


/**
 * @author Andreas Åkre Solberg <andreas.solberg@uninett.no>
 * @package SimpleSAMLphp
 */
class sspmod_statistics_StatDataset
{

    protected $statconfig;
    protected $ruleconfig;
    protected $timeresconfig;
    protected $ruleid;

    protected $fileslot;
    protected $timeres;

    protected $delimiter;
    protected $results;
    protected $summary;
    protected $max;

    protected $datehandlerFile;
    protected $datehandlerTick;


    /**
     * Constructor
     */
    public function __construct($statconfig, $ruleconfig, $ruleid, $timeres, $fileslot)
    {
        assert('$statconfig instanceof SimpleSAML_Configuration');
        assert('$ruleconfig instanceof SimpleSAML_Configuration');
        $this->statconfig = $statconfig;
        $this->ruleconfig = $ruleconfig;

        $timeresconfigs = $statconfig->getConfigItem('timeres');
        $this->timeresconfig = $timeresconfigs->getConfigItem($timeres);

        $this->ruleid = $ruleid;
        $this->fileslot = $fileslot;
        $this->timeres = $timeres;

        $this->delimiter = '_';
        $this->max = 0;

        $this->datehandlerTick = new sspmod_statistics_DateHandler($this->statconfig->getValue('offset', 0));
        if ($this->timeresconfig->getValue('customDateHandler', 'default') === 'month') {
            $this->datehandlerFile = new sspmod_statistics_DateHandlerMonth(0);
        } else {
            $this->datehandlerFile = $this->datehandlerTick;
        }

        $this->loadData();
    }


    public function getFileSlot()
    {
        return $this->fileslot;
    }


    public function getTimeRes()
    {
        return $this->timeres;
    }


    public function setDelimiter($delimiter = '_')
    {
        if (empty($delimiter)) {
            $delimiter = '_';
        }
        $this->delimiter = $delimiter;
    }


    public function getDelimiter()
    {
        if ($this->delimiter === '_') {
            return null;
        }
        return $this->delimiter;
    }


    public function calculateMax()
    {
        $maxvalue = 0;
        foreach ($this->results as $slot => &$res) {
            if (!array_key_exists($this->delimiter, $res)) {
                $res[$this->delimiter] = 0;
            }
            $maxvalue = max($res[$this->delimiter], $maxvalue);
        }
        $this->max = sspmod_statistics_Graph_GoogleCharts::roof($maxvalue);
    }


    public function getDebugData()
    {
        $debugdata = array();

        $slotsize = $this->timeresconfig->getValue('slot');
        $dateformat_intra = $this->timeresconfig->getValue('dateformat-intra');

        foreach ($this->results as $slot => &$res) {
            $debugdata[$slot] = array(
                $this->datehandlerTick->prettyDateSlot($slot, $slotsize, $dateformat_intra),
                $res[$this->delimiter]
            );
        }
        return $debugdata;
    }


    public function aggregateSummary()
    {
        // aggregate summary table from dataset. To be used in the table view
        $this->summary = array();
        foreach ($this->results as $slot => $res) {
            foreach ($res as $key => $value) {
                if (array_key_exists($key, $this->summary)) {
                    $this->summary[$key] += $value;
                } else {
                    $this->summary[$key] = $value;
                }
            }
        }
        asort($this->summary);
        $this->summary = array_reverse($this->summary, true);
    }


    public function getTopDelimiters()
    {
        // create a list of delimiter keys that has the highest total summary in this period
        $topdelimiters = array();
        $maxdelimiters = 4;
        $i = 0;
        foreach ($this->summary as $key => $value) {
            if ($key !== '_') {
                $topdelimiters[] = $key;
            }
            if ($i++ >= $maxdelimiters) {
                break;
            }
        }
        return $topdelimiters;
    }


    public function availDelimiters()
    {
        $availDelimiters = array();
        foreach ($this->summary as $key => $value) {
            $availDelimiters[$key] = 1;
        }
        return array_keys($availDelimiters);
    }


    public function getPieData()
    {
        $piedata = array();
        $sum = 0;
        $topdelimiters = $this->getTopDelimiters();

        foreach ($topdelimiters as $td) {
            $sum += $this->summary[$td];
            $piedata[] = number_format(100 * $this->summary[$td] / $this->summary['_'], 2);
        }
        $piedata[] = number_format(100 - 100 * ($sum / $this->summary['_']), 2);
        return $piedata;
    }


    public function getMax()
    {
        return $this->max;
    }


    public function getSummary()
    {
        return $this->summary;
    }


    public function getResults()
    {
        return $this->results;
    }


    public function getAxis()
    {
        $slotsize = $this->timeresconfig->getValue('slot');
        $dateformat_period = $this->timeresconfig->getValue('dateformat-period');
        $dateformat_intra = $this->timeresconfig->getValue('dateformat-intra');
        $axislabelint = $this->timeresconfig->getValue('axislabelint');

        $axis = array();
        $axispos = array();
        $xentries = count($this->results);
        $lastslot = 0;
        $i = 0;

        foreach ($this->results as $slot => $res) {
            // check if there should be an axis here...
            if ($slot % $axislabelint == 0) {
                $axis[] = $this->datehandlerTick->prettyDateSlot($slot, $slotsize, $dateformat_intra);
                $axispos[] = (($i) / ($xentries - 1));
            }
            $lastslot = $slot;
            $i++;
        }

        $axis[] = $this->datehandlerTick->prettyDateSlot($lastslot + 1, $slotsize, $dateformat_intra);

        return array('axis' => $axis, 'axispos' => $axispos);
    }


    /*
     * Walk through dataset to get percent values from max into dataset[].
     */
    public function getPercentValues()
    {
        $i = 0;
        $dataset = array();
        foreach ($this->results as $slot => $res) {
            if (array_key_exists($this->delimiter, $res)) {
                if ($res[$this->delimiter] === null) {
                    $dataset[] = -1;
                } else {
                    $dataset[] = number_format(100 * $res[$this->delimiter] / $this->max, 2);
                }
            } else {
                $dataset[] = '0';
            }
            $i++;
        }

        return $dataset;
    }


    public function getDelimiterPresentation()
    {
        $config = SimpleSAML_Configuration::getInstance();
        $t = new SimpleSAML_XHTML_Template($config, 'statistics:statistics-tpl.php');

        $availdelimiters = $this->availDelimiters();

        // create a delimiter presentation filter for this rule...
        if ($this->ruleconfig->hasValue('fieldPresentation')) {
            $fieldpresConfig = $this->ruleconfig->getConfigItem('fieldPresentation');
            $classname = SimpleSAML_Module::resolveClass(
                $fieldpresConfig->getValue('class'),
                'Statistics_FieldPresentation'
            );
            if (!class_exists($classname)) {
                throw new Exception('Could not find field presentation plugin ['.$classname.']: No class found');
            }
            $presentationHandler = new $classname($availdelimiters, $fieldpresConfig->getValue('config'), $t);

            return $presentationHandler->getPresentation();
        }

        return array();
    }


    public function getDelimiterPresentationPie()
    {
        $topdelimiters = $this->getTopDelimiters();
        $delimiterPresentation = $this->getDelimiterPresentation();

        $pieaxis = array();
        foreach ($topdelimiters as $key) {
            $keyName = $key;
            if (array_key_exists($key, $delimiterPresentation)) {
                $keyName = $delimiterPresentation[$key];
            }
            $pieaxis[] = $keyName;
        }
        $pieaxis[] = 'Others';
        return $pieaxis;
    }


    public function loadData()
    {
        $statdir = $this->statconfig->getValue('statdir');
        $resarray = array();
        $rules = SimpleSAML\Utils\Arrays::arrayize($this->ruleid);
        foreach ($rules as $rule) {
            // Get file and extract results.
            $resultFileName = $statdir.'/'.$rule.'-'.$this->timeres.'-'.$this->fileslot.'.stat';
            if (!file_exists($resultFileName)) {
                throw new Exception('Aggregated statitics file ['.$resultFileName.'] not found.');
            }
            if (!is_readable($resultFileName)) {
                throw new Exception('Could not read statitics file ['.$resultFileName.']. Bad file permissions?');
            }
            $resultfile = file_get_contents($resultFileName);
            $newres = unserialize($resultfile);
            if (empty($newres)) {
                throw new Exception('Aggregated statistics in file ['.$resultFileName.'] was empty.');
            }
            $resarray[] = $newres;
        }

        $combined = $resarray[0];
        $count = count($resarray);
        if ($count > 1) {
            for ($i = 1; $i < $count; $i++) {
                $combined = $this->combine($combined, $resarray[$i]);
            }
        }
        $this->results = $combined;
    }

}

