<?php

class OWMonitoringReport extends eZPersistentObject {

    protected $identifier;
    protected $reportData;
    protected $date;
    protected $serialized_data;

    public function __construct( $identifier ) {
        if( empty( $identifier ) ) {
            throw new OWMonitoringReportException( __METHOD__ . " : Report identifier must be set" );
        }
        $this->identifier = $identifier;
        $this->reportData = array( );
        if( !empty( $this->serialized_data ) ) {
            $this->reportData = unserialize( $this->attribute( 'serialized_data' ) );
        }
    }

    public function getIdentifier( ) {
        return $this->identifier;
    }

    public function hasData( $name ) {
        return array_key_exists( $name, $this->reportData );
    }

    public function getData( $name ) {
        if( $this->hasData( $name ) ) {
            if( count( $this->reportData[$name] ) == 1 ) {
                return $this->reportData[$name][0];
            } else {
                return $this->reportData[$name];
            }
        }
        return NULL;
    }

    public function setData( $name, $data, $clock = null ) {
        if( !is_string( $name ) ) {
            return FALSE;
        }
        if( !is_array( $data ) ) {
            $data = array( $data );
        }
        $newData = array( );
        foreach( $data as $dataItem ) {
            $newDataItem = array( 'data' => $dataItem );
            if( $clock ) {
                $newDataItem['clock'] = $clock;
            }
            $newData[] = $newDataItem;
        }

        $this->reportData[$name] = $newData;

        return TRUE;
    }

    public function appendToData( $name, $data, $clock = null ) {
        if( $this->hasData( $name ) ) {
            $currentData = $this->reportData[$name];
            $this->setData( $name, $data, $clock );
            $newData = $this->reportData[$name];
            $data = array_merge( $currentData, $newData );
            $this->reportData[$name] = $data;
        } else {
            $this->setData( $name, $data, $clock );
        }
    }

    public function deleteData( $name ) {
        if( $this->hasData( $name ) ) {
            unset( $this->reportData[$name] );
        }
    }

    public function getDatas( ) {
        return $this->reportData;
    }

    public function countDatas( ) {
        return count( $this->reportData );
    }

    public function setDatas( $dataArray, $clock = null ) {
        foreach( $dataArray as $name => $data ) {
            $this->setData( $name, $data, $clock );
        }
    }

    public function sendReport( ) {
        $INI = eZINI::instance( 'owmonitoring.ini' );
        if( !$INI->hasVariable( 'OWMonitoring', 'MonitoringToolClass' ) ) {
            OWMonitoringLogger::logError( __METHOD__ . " : [OWMonitoring]MonitoringToolClass not defined in owmonitoring.ini" );
            return FALSE;
        }
        $monitoringToolClass = $INI->variable( 'OWMonitoring', 'MonitoringToolClass' );
        if( !class_exists( $monitoringToolClass ) ) {
            OWMonitoringLogger::logError( __METHOD__ . " : Class $monitoringToolClass not found" );
            return FALSE;
        }
        $tool = $monitoringToolClass::instance( );
        return $tool->sendReport( $this );
    }

    static function prepareReport( $reportName ) {
        $INI = eZINI::instance( 'owmonitoring.ini' );
        if( $INI->hasVariable( $reportName, 'Identifier' ) ) {
            $identifier = $INI->variable( $reportName, 'Identifier' );
        } else {
            throw new OWMonitoringReportException( __METHOD__ . " : [$reportName]Identifier not defined in owmonitoring.ini" );
        }
        if( $INI->hasVariable( $reportName, 'PrepareFrequency' ) ) {
            $prepareFrequency = $INI->variable( $reportName, 'PrepareFrequency' );
            $date = new DateTime( );
            switch( $prepareFrequency ) {
                case 'minute' :
                    $fromDate = $date->format( 'Y-m-d H:i:00' );
                    $date->modify( '+1 minute' );
                    $toDate = $date->format( 'Y-m-d H:i:00' );
                    break;
                case 'quarter_hour' :
                    $quarter = intval( $date->format( 'i' ) / 15 );
                    $fromDate = $date->format( 'Y-m-d H:' . (15 * $quarter) . ':00' );
                    $quarter++;
                    $toDate = $date->format( 'Y-m-d H:' . (15 * $quarter) . ':00' );
                    break;
                case 'houly' :
                    $fromDate = $date->format( 'Y-m-d H:00:00' );
                    $date->modify( '+1 hour' );
                    $toDate = $date->format( 'Y-m-d H:00:00' );
                    break;
                case 'daily' :
                    $fromDate = $date->format( 'Y-m-d 00:00:00' );
                    $date->modify( '+1 day' );
                    $toDate = $date->format( 'Y-m-d 00:00:00' );
                    break;
                case 'weekly' :
                    $fromDate = $date->format( 'Y-m-d 00:00:00' );
                    $date->modify( '+1 week' );
                    $toDate = $date->format( 'Y-m-d 00:00:00' );
                    break;
                case 'monthly' :
                    $fromDate = $date->format( 'Y-m-01 00:00:00' );
                    $date->modify( '+1 month' );
                    $toDate = $date->format( 'Y-m-01 00:00:00' );
                    break;
                default :
                    throw new OWMonitoringReportException( __METHOD__ . " : bad frequency" );
                    break;
            }
            if( self::fetchCount( $identifier, $fromDate, $toDate ) > 0 ) {
                throw new OWMonitoringReportException( __METHOD__ . " : $reportName already exits" );
            }
            $report = self::makeReport( $reportName, TRUE );
            $report->store();
        }
    }

    static function makeReport( $reportName, $forceClock = NULL ) {
        $INI = eZINI::instance( 'owmonitoring.ini' );
        if( $INI->hasVariable( $reportName, 'Identifier' ) ) {
            $identifier = $INI->variable( $reportName, 'Identifier' );
        } else {
            throw new OWMonitoringReportException( __METHOD__ . " : [$reportName]Identifier not defined in owmonitoring.ini" );
        }
        if( $INI->hasVariable( $reportName, 'Tests' ) ) {
            $testList = $INI->variable( $reportName, 'Tests' );
        } else {
            throw new OWMonitoringReportException( __METHOD__ . " : [$reportName]Tests not defined in owmonitoring.ini" );
        }
        try {
            $report = new OWMonitoringReport( $identifier );
        } catch( Exception $e ) {
            throw new OWMonitoringReportException( __METHOD__ . " : Report instancation failed\n" . $e->getMessage( ) );
        }
        if( $forceClock === TRUE ) {
            $forceClock = time( );
        }
        foreach( $testList as $testIdentifier => $testFunction ) {
            list( $testClass, $testMethod ) = explode( '::', $testFunction );
            $testClass = $reportName . '_' . $testClass;
            $testMethod = 'test' . $testMethod;
            $testFunction = $testClass . '::' . $testMethod;
            if( !class_exists( $testClass ) ) {
                OWMonitoringLogger::logError( __METHOD__ . " : Class $testClass not found" );
                continue;
            } elseif( !is_callable( $testFunction ) ) {
                OWMonitoringLogger::logError( __METHOD__ . " : Can not call $testFunction method" );
                continue;
            } else {
                try {
                    $testValue = call_user_func( $testFunction );
                } catch (  Exception $e ) {
                    OWMonitoringLogger::logError( __METHOD__ . " : " . $e->getMessage( ) );
                    $testValue = FALSE;
                }
                $report->setData( $testIdentifier, $testValue, $forceClock );
            }
        }
        if( $report->countDatas( ) == 0 ) {
            throw new OWMonitoringReportException( __METHOD__ . " : Report is empty" );
        }
        return $report;
    }

    /* eZPersistentObject methods */

    public static function definition( ) {
        return array(
            'fields' => array(
                'identifier' => array(
                    'name' => 'identifier',
                    'datatype' => 'string',
                    'default' => null,
                    'required' => true
                ),
                'date' => array(
                    'name' => 'date',
                    'datatype' => 'string',
                    'default' => null,
                    'required' => true
                ),
                'serialized_data' => array(
                    'name' => 'serialized_data',
                    'datatype' => 'text',
                    'default' => null,
                    'required' => true
                )
            ),
            'keys' => array(
                'identifier',
                'date'
            ),
            'class_name' => 'OWMonitoringReport',
            'name' => 'owmonitoring_report',
            'function_attributes' => array( ),
            'set_functions' => array( )
        );
    }

    function store( $fieldFilters = NULL ) {
        $this->setAttribute( 'serialized_data', serialize( $this->reportData ) );
        $this->setAttribute( 'date', date( 'Y-m-d H:i:s' ) );
        parent::store( $fieldFilters );
    }

    static function fetch( $identifier, $fromDate = NULL, $toDate = NULL ) {
        $rows = self::fetchList( $identifier, $fromDate, $toDate, 1 );
        if( $rows )
            return $rows[0];
        return null;
    }

    static function fetchList( $identifier = NULL, $fromDate = NULL, $toDate = NULL, $limit = NULL ) {
        $conds = array( );
        if( $identifier ) {
            $conds[] = "identifier LIKE '$identifier'";
        }
        if( $fromDate ) {
            $conds[] = "date >= '$fromDate'";
        }
        if( $toDate ) {
            $conds[] = "date < '$toDate'";
        }
        if( !empty( $conds ) ) {
            $conds = ' WHERE ' . implode( ' AND ', $conds );
        }
        return self::fetchObjectList( self::definition( ), null, null, array(
            'identifier' => 'asc',
            'date' => 'asc'
        ), $limit, true, false, null, null, $conds );
    }

    static function fetchCount( $identifier = NULL, $fromDate = NULL, $toDate = NULL ) {
        return count( self::fetchList( $identifier, $fromDate, $toDate ) );
    }

}
