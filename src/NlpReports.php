<?php /** @noinspection PhpUnused */

namespace Drupal\nlpservices;

use Drupal;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Database\Connection;
use Exception;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\Core\Database;


class NlpReports {
  
  const NLP_RESULTS_TBL = 'nlp_results';
  const BATCH_LIMIT = 100;
  
  const CONTACT = 'contact';
  const SURVEY = 'Survey';
  const ID = 'ID';
  const COMMENT = 'Comment';
  
  const moved = 'moved';
  const POSTCARD = 'Mailed postcard';
  
  public const MAX_COMMENT = '190';
  
  const MULTI_INSERT = 100;
  const BATCH = 10;

  private array $records = array();
  private int $sqlCnt = 0;
  private int $batchCnt = 0;


  //private array $reportFields = ['reportIndex','cycle','county','active','vanid','mcid','contactDate',
  private array $reportFields = ['cycle','county','active','vanid','mcid','contactDate',
    'contactType','type','value','text','qid','rid','cid','contactId'];

  private array $reportFieldType = array(
    //'reportIndex'=>'int',
    'cycle'=>'char',
    'county'=>'char',
    'active'=>'int',
    'vanid'=>'int',
    'mcid'=>'int',
    'contactDate'=>'date',
    'contactType'=>'char',
    'type'=>'char',
    'value'=>'char',
    'text'=>'text',
    'qid'=>'int',
    'rid'=>'int',
    'cid'=>'int',
    'contactId'=>'int',
  );
  
  protected ConfigFactoryInterface $nlpConfig;
  protected Connection $connection;
  
  public function __construct( $nlpConfig,  $connection) {
    $this->nlpConfig = $nlpConfig;
    $this->connection = $connection;
  }
  
  public static function create(ContainerInterface $container): NlpReports
  {
    return new static(
      $container->get('config.factory'),
      $container->get('database'),
    );
  }
  
  public function getNlpTypeReports($vanid,$type) {
    //nlp_debug_msg('vanid', $vanid);
    $config = Drupal::config('nlpservices.configuration');
    $electionConfiguration = $config->get('nlpservices-election-configuration');
    $cycle = $electionConfiguration['nlp_election_cycle'];
    
    try {
      $query = $this->connection->select(self::NLP_RESULTS_TBL, 'v');
      $query->fields('v');
      $query->condition('vanid',$vanid);
      $query->condition('active',TRUE);
      $query->condition('type',$type);
      $query->condition('cycle',$cycle);
      $query->orderBy('contactDate', 'DESC');
      $result = $query->execute();
    }
    catch (Exception $e) {
      nlp_debug_msg('e', $e->getMessage() );
      return '';
    }
    
    $voterReports = [];
    do {
      $report = $result->fetchAssoc();
      if(!$report) {break;}
      $voterReports[] = $report;
    } while (TRUE);
    return $voterReports;
  }
  
  public function getNlpReports($vanid) {
    try {
      $query = $this->connection->select(self::NLP_RESULTS_TBL, 'v');
      $query->fields('v');
      $query->condition('vanid',$vanid);
      $query->condition('active',TRUE);
      $query->orderBy('contactDate', 'DESC');
      $result = $query->execute();
    }
    catch (Exception $e) {
      nlp_debug_msg('e', $e->getMessage() );
      return '';
    }
    
    $voterReports = array();
    do {
      $report = $result->fetchAssoc();
      //nlp_debug_msg('$report',$report);
      if(!$report) {break;}
      $voterReports[$report['vanid']][] = $report;
    } while (TRUE);
    return $voterReports;
  }
  
  public function getNlpVoterReports($mcid,$cycle) {
    try {
      $query = $this->connection->select(self::NLP_RESULTS_TBL, 'v');
      $query->fields('v');
      $query->condition('mcid',$mcid);
      $query->condition('active',TRUE);
      $query->condition('type','Activist','<>');
      if(!empty($cycle)) {
        $query->condition('cycle',$cycle);
      }
      $query->orderBy('contactDate', 'DESC');
      $result = $query->execute();
    }
    catch (Exception $e) {
      nlp_debug_msg('e', $e->getMessage() );
      return '';
    }
    
    $voterReports = $voterReport = array();
    do {
      $report = $result->fetchAssoc();
      if(!$report) {break;}
      $voterReports[$report['mcid']][] = $voterReport;
    } while (TRUE);
    return $voterReports;
  }
  
  public function getNlReportsForVoters($voterArray,$cycle){
    try {
      $query = $this->connection->select(self::NLP_RESULTS_TBL, 'v');
      $query->fields('v');
      $query->condition('vanid', $voterArray, 'IN');
      $query->condition('active',TRUE);
      $query->condition('cycle',$cycle);
      $query->condition('type','Activist','<>');
      $query->orderBy('contactDate', 'DESC');
      $result = $query->execute();
    }
    catch (Exception $e) {
      nlp_debug_msg('e', $e->getMessage() );
      return '';
    }
    
    $voterReports = array();
    do {
      $report = $result->fetchAssoc();
      if(!$report) {break;}
      $voterReports[$report['mcid']][] = $report;
    } while (TRUE);
    
    return $voterReports;
  }
 
  public function reportExists($type,$contactId): bool
  {
    //nlp_debug_msg('$contactId',$contactId);
    $query = $this->connection->select(self::NLP_RESULTS_TBL, 'r');
    $query->fields('r');
    $query->condition('type',$type);
    $query->condition('contactID',$contactId);
    $result = $query->execute();
    $report = $result->fetchAssoc();
    //nlp_debug_msg('$report',$report);
    if(empty($report)) {return FALSE;}
    return TRUE;
  }
  
  public function displayNlReports($voterReports): array
  {
    $voterReportsDisplay = array();
    $voterReportsDisplay['display'] = '';
    $voterReportsDisplay['displayLines'] = 0;
    if(empty($voterReports)) {return $voterReportsDisplay;}
    foreach ($voterReports as $reports) {
      foreach ($reports as $report) {
        if(empty($report['type'])) {continue;}
        $reportType = strtolower($report['type']);
        switch ($reportType) {
          case 'id':
            // For the ID, add the candidate name.
            $reportDisplay = $report['text'].' ['.$report['value'].']';
            break;
          case 'contact':
            $reportDisplay = $report['value'].' '.$report['text'];
            break;
          case 'comment':
            $reportDisplay = $report['text'];
            break;
          case 'survey':
            $reportDisplay = $report['text'].': '.$report['value'];
            break;
          case 'activist':
            $activistCodeName = $report['text'];
            $activistCodeValue = $report['value'];
            $activistDisplay = ($activistCodeValue)?'Yes':'No';
            $reportDisplay = $activistCodeName.': '.$activistDisplay;
            break;
        }
        if(!empty($reportDisplay)) {
          if (!empty($voterReportsDisplay['display'])) {
            $voterReportsDisplay['display'] .= '<br/>';
          }
          $newReport = $report['contactDate'].'['.$report['contactType'].'] '.$reportDisplay;
          $voterReportsDisplay['display'] .= $newReport;
          $lines = ceil(strlen($newReport)/60);
          $voterReportsDisplay['displayLines'] = $voterReportsDisplay['displayLines'] + $lines;
        }
      }
    }
    return $voterReportsDisplay;
  }
  
  public function getNlpReport($reportIndex): array
  {
    try {
      //$query = db_select(self::NLP_RESULTS_TBL, 'v');
      $query = $this->connection->select(self::NLP_RESULTS_TBL, 'v');
      $query->fields('v');
      $query->condition('reportIndex',$reportIndex);
      $result = $query->execute();
    }
    catch (Exception $e) {
      nlp_debug_msg('e', $e->getMessage() );
      return [];
    }
    $report = $result->fetchAssoc();
    if(empty($report)) return [];
    return $report;
  }
  
  public function setNlReport($canvassResult) {
    // Insert the reported information into the results table.
    if(!empty($canvassResult['contactId'])) {
      $contactId = $canvassResult['contactId'];
    } else {
      $contactId = NULL;
    }
    try {
      $reportIndex = $this->connection->insert(self::NLP_RESULTS_TBL)
        ->fields(array(
          'cycle' => $canvassResult['cycle'],
          'county' => $canvassResult['county'],
          'active' => TRUE,
          'mcid' => $canvassResult['mcid'],
          'vanid' => $canvassResult['vanid'],
          'contactDate' => $canvassResult['contactDate'],
          'contactType' => $canvassResult['contactType'],
          'type' => $canvassResult['type'],
          'value' => $canvassResult['value'],
          'text' => $canvassResult['text'],
          'qid' => $canvassResult['qid'],
          'rid' => $canvassResult['rid'],
          'cid' => $canvassResult['cid'],
          'contactId' => $contactId,
        ))
        ->execute();
    } catch (Exception $e) {
      nlp_debug_msg('e', $e->getMessage() );
      return 0;
    }
    //nlp_debug_msg('$reportIndex',$reportIndex);
    return $reportIndex;
  }
  
  public function updateComment($canvassResult) {
    try {
      $this->connection->update(self::NLP_RESULTS_TBL)
      //db_update(self::NLP_RESULTS_TBL)
        ->fields(array(
          'text' => $canvassResult['text'],
        ))
        ->condition('reportIndex',$canvassResult['reportIndex'])
        ->execute();
    }
    catch (Exception $e) {
      nlp_debug_msg('e', $e->getMessage() );
      return;
    }
  }
  
  public function getComment($req): array
  {
    $config = Drupal::config('nlpservices.configuration');
    $electionConfiguration = $config->get('nlpservices-election-configuration');
    $cycle = $electionConfiguration['nlp_election_cycle'];
    try {
      //$query = db_select(self::NLP_RESULTS_TBL, 'v');
      $query = $this->connection->select(self::NLP_RESULTS_TBL, 'v');
      $query->addField('v','text');
      $query->addField('v','reportIndex');
      $query->condition('cycle',$cycle);
      $query->condition('mcid',$req['mcid']);
      $query->condition('vanid',$req['vanid']);
      $query->condition('type','Comment');
      $result = $query->execute();
    }
    catch (Exception $e) {
      nlp_debug_msg('e', $e->getMessage() );
      return array();
    }
    $newestComment = array('reportIndex'=>NULL,'text'=>'');
    do {
      $comment = $result->fetchAssoc();
      if(empty($comment)) {break;}
      if($comment['reportIndex'] >= $newestComment['reportIndex']) {
        if($comment['text'][0] == '_' AND $comment['text'][3] == ':') {continue;}  // check for tag.
        $newestComment = $comment;
      }
    } while (TRUE);
    return array('reportIndex'=>$newestComment['reportIndex'],'text'=>$newestComment['text']);
  }
  
  public function getNlpAcReport($vanId) {
    try {
      //$query = db_select(self::NLP_RESULTS_TBL, 'v');
      $query = $this->connection->select(self::NLP_RESULTS_TBL, 'v');
      $query->fields('v');
      $query->condition('vanid',$vanId);
      $query->condition('active',TRUE);
      $query->condition('type','Activist');
      $query->orderBy('contactDate', 'DESC');
      $result = $query->execute();
    }
    catch (Exception $e) {
      nlp_debug_msg('e', $e->getMessage() );
      return '';
    }
    $report = $result->fetchAssoc();
    if(!$report) {return NULL;}
    $acName = $report['text'];
    $voterReport[$acName] = $report;
    return $voterReport;
  }
  
  public function getAcReportIndex($vanid,$cycle,$activistCodeName): int
  {
    try {$query = $this->connection->select(self::NLP_RESULTS_TBL, 'r');
      $query->addField('r','reportIndex');
      $query->condition('vanid',$vanid);
      $query->condition('cycle',$cycle);
      $query->condition('active',TRUE);
      $query->condition('type','Activist');
      $query->condition('text',$activistCodeName);
      $result = $query->execute();
    }
    catch (Exception $e) {
      nlp_debug_msg('e', $e->getMessage() );
      return 0;
    }
    $report = $result->fetchAssoc();
    if(!$report) {return 0;}
    return $report['reportIndex'];
  }
  
  public function deleteReport($reportIndex) {
    $this->connection->delete(self::NLP_RESULTS_TBL)
      ->condition('reportIndex', $reportIndex)
      ->execute();
  }
  
  public function getReport($request) {
    // Get the request reports for this voter.
    try {
      $query = $this->connection->select(self::NLP_RESULTS_TBL, 'v');
      $query->fields('v');
      $query->condition('vanid',$request['vanid']);
      $query->condition('active',TRUE);
      $query->condition('type',$request['type']);
      $query->condition('value',$request['value']);
      if(!empty($request['cycle'])) {
        $query->condition('cycle',$request['cycle']);
      }
      $query->orderBy('reportIndex');
      $result = $query->execute();
    }
    catch (Exception $e) {
      nlp_debug_msg('e', $e->getMessage() );
      return '';
    }
    
    // Now find the newest report.
    $report = [];
    $reportIndex = 0;
    do {
      $dbReport = $result->fetchAssoc();
      if(!$dbReport) {break;}
      if($dbReport['reportIndex'] > $reportIndex) {
        $reportIndex = $dbReport['reportIndex'];
        $report = $dbReport;
      }
    } while (TRUE);
    return $report;
  }
  
  public function mergeReport($canvassResult): bool
  {
    //nlp_debug_msg('$canvassResult',$canvassResult);
    $reportIndex = $canvassResult['reportIndex'];
    $fields = array();
    $reportFields = array_keys($this->reportFieldType);
    //unset($reportFields[0]);
    foreach ($reportFields as $key) {
      if(isset($canvassResult[$key])) {
        $fields[$key] = $canvassResult[$key];
      }
    }
    //('$fields',$fields);
    try {
      $this->connection->merge(self::NLP_RESULTS_TBL)
        ->keys(array('reportIndex' => $reportIndex))
        ->fields($fields)
        ->execute();
    }
    catch (Exception $e) {
      nlp_debug_msg('e', $e->getMessage());
      return FALSE;
    }
    return TRUE;
  }
  
  public function setNlAcReport($canvassResult): bool
  {
    $reportIndex = $canvassResult['reportIndex'];
    try {
      $this->connection->merge(self::NLP_RESULTS_TBL)
        ->keys(array('reportIndex' => $reportIndex))
        ->fields($canvassResult)
        ->execute();
    }
    catch (Exception $e) {
      nlp_debug_msg('e', $e->getMessage());
      return FALSE;
    }
    return TRUE;
  }
  
  function surveyResponse($vanid): bool
  {
    //$config = $this->nlpConfig('nlpservices.configuration');
    $electionConfiguration = $this->nlpConfig->get('nlpservices-election-configuration');
    $cycle = $electionConfiguration['nlp_election_cycle'];
    try {
      $query = $this->connection->select(self::NLP_RESULTS_TBL, 'v');
      $query->fields('v');
      $query->condition('cycle',$cycle);
      $query->condition('vanid',$vanid);
      $query->condition('type',self::SURVEY);
      $query->range(0,1);
      $result = $query->execute();
    }
    catch (Exception $e) {
      nlp_debug_msg('e', $e->getMessage() );
      return FALSE;
    }
    $report = $result->fetchAssoc();
    if(empty($report)) {return FALSE;}
    return TRUE;
  }
 
  function contactAttempt($vanid): array
  {
    //$config = $this->config('nlpservices.configuration');

    //$electionConfiguration = $this->nlpConfig->get('nlpservices-election-configuration');
    //$cycle = $electionConfiguration['nlp_election_cycle'];

    $config = Drupal::service('config.factory')->get('nlpservices.configuration');
    $electionDates = $config->get('nlpservices-election-configuration');
    $cycle = $electionDates['nlp_election_cycle'];

    $canvassResult['attempt'] = $canvassResult['survey'] = FALSE;
    try {
      $query = $this->connection->select(self::NLP_RESULTS_TBL, 'r');
      $query->fields('r');
      $query->condition('cycle',$cycle);
      $query->condition('vanid',$vanid);
  
      $orGroup1 = $query->orConditionGroup()
        ->condition ('r.Type', self::SURVEY )
        ->condition ('r.Type', self::CONTACT );
      $query->condition($orGroup1);
      $result = $query->execute();
    }
    catch (Exception $e) {
      nlp_debug_msg('e', $e->getMessage() );
      return $canvassResult;
    }
    do {
      $report = $result->fetchAssoc();
      if(empty($report)) {break;}
      $canvassResult['attempt'] = TRUE;
      if($report['type'] == 'Survey') {
        $canvassResult['survey'] = TRUE;
      }
    } while (TRUE);
    return $canvassResult;
  }
  
  function voterContacted($vanid): bool
  {
    $config = Drupal::config('nlpservices.configuration');
    $electionConfiguration = $config->get('nlpservices-election-configuration');
    $cycle = $electionConfiguration['nlp_election_cycle'];
    try {
      //$query = db_select(self::NLP_RESULTS_TBL, 'r');
      $query = $this->connection->select(self::NLP_RESULTS_TBL, 'r');
      $query->addField('r','vanid');
      $query->condition('vanid',$vanid);
      $query->condition('cycle',$cycle);
      $query->condition('type',self::SURVEY);
      $contactCount = $query->countQuery()->execute()->fetchField();
    }
    catch (Exception $e) {
      nlp_debug_msg('e', $e->getMessage() );
      return FALSE;
    }
    return $contactCount>0;
  }

  function voterContactAttempted($vanid): bool
  {
    $config = Drupal::config('nlpservices.configuration');
    $electionConfiguration = $config->get('nlpservices-election-configuration');
    $cycle = $electionConfiguration['nlp_election_cycle'];
    try {
      //$query = db_select(self::NLP_RESULTS_TBL, 'r');
      $query = $this->connection->select(self::NLP_RESULTS_TBL, 'r');
      $query->addField('r','vanid');
      $query->condition('vanid',$vanid);
      $query->condition('cycle',$cycle);
      //$query->condition('type',self::SURVEY);
      $orGroup = $query->orConditionGroup()
        ->condition ('r.Type', self::SURVEY )
        ->condition ('r.Type', self::CONTACT );
      $query->condition($orGroup);
      $contactCount = $query->countQuery()->execute()->fetchField();
    }
    catch (Exception $e) {
      nlp_debug_msg('e', $e->getMessage() );
      return FALSE;
    }
    return $contactCount>0;
  }

  function voterSentPostcard($vanid): bool
  {
    $config = Drupal::config('nlpservices.configuration');
    $electionConfiguration = $config->get('nlpservices-election-configuration');
    $cycle = $electionConfiguration['nlp_election_cycle'];
    try {
      //$query = db_select(self::NLP_RESULTS_TBL, 'r');
      $query = $this->connection->select(self::NLP_RESULTS_TBL, 'r');
      $query->addField('r','vanid');
      $query->condition('vanid',$vanid);
      $query->condition('cycle',$cycle);
      $query->condition('type',self::CONTACT);
      //$query->condition('value',self::POSTCARD);
      $orGroup = $query->orConditionGroup()
        ->condition('value',self::POSTCARD)
        ->condition('value','Mailed');
      $query->condition($orGroup);
      $contactCount = $query->countQuery()->execute()->fetchField();
    }
    catch (Exception $e) {
      nlp_debug_msg('e', $e->getMessage() );
      return FALSE;
    }
    return $contactCount>0;
  }
  
  function countyContacted($county): int
  {
    $config = Drupal::config('nlpservices.configuration');
    $electionConfiguration = $config->get('nlpservices-election-configuration');
    $cycle = $electionConfiguration['nlp_election_cycle'];
    try {
      $query = $this->connection->select(self::NLP_RESULTS_TBL, 'r');
      $query->addField('r','vanid');
      $query->distinct();
      $query->condition('cycle',$cycle);
      $query->condition('county',$county);
      $query->condition('type',self::SURVEY);
      $contactedCount = $query->countQuery()->execute()->fetchField();
    }
    catch (Exception $e) {
      nlp_debug_msg('e', $e->getMessage() );
      return 0;
    }
    return $contactedCount;
  }

  function countyAttempted($county): int
  {
    $config = Drupal::config('nlpservices.configuration');
    $electionConfiguration = $config->get('nlpservices-election-configuration');
    $cycle = $electionConfiguration['nlp_election_cycle'];
    try {
      $query = $this->connection->select(self::NLP_RESULTS_TBL, 'r');
      $query->addField('r','vanid');
      $query->distinct();
      $query->condition('cycle',$cycle);
      $query->condition('county',$county);
      $orGroup = $query->orConditionGroup()
        ->condition('type',self::SURVEY)
        ->condition('type',self::CONTACT);
      $query->condition($orGroup);
      $contactedCount = $query->countQuery()->execute()->fetchField();
    }
    catch (Exception $e) {
      nlp_debug_msg('countyAttempted', $e->getMessage() );
      return 0;
    }
    return $contactedCount;
  }
  
  function countyContactedByPostcard($county): int
  {
    $config = Drupal::config('nlpservices.configuration');
    $electionConfiguration = $config->get('nlpservices-election-configuration');
    $cycle = $electionConfiguration['nlp_election_cycle'];
    try {
      //$query = db_select(self::NLP_RESULTS_TBL, 'r');
      $query = $this->connection->select(self::NLP_RESULTS_TBL, 'r');
      $query->addField('r','vanid');
      $query->distinct();
      $query->condition('cycle',$cycle);
      $query->condition('county',$county);
      $query->condition('type',self::CONTACT);
      //$query->condition('value',self::POSTCARD);
      $orGroup = $query->orConditionGroup()
        ->condition('value',self::POSTCARD)
        ->condition('value','Mailed');
      $query->condition($orGroup);
      $contactedCount = $query->countQuery()->execute()->fetchField();
    }
    catch (Exception $e) {
      nlp_debug_msg('e', $e->getMessage() );
      return 0;
    }
    return $contactedCount;
  }
  
  public function getCountyReportCounts($county) {
    $config = Drupal::config('nlpservices.configuration');
    $electionConfiguration = $config->get('nlpservices-election-configuration');
    $cycle = $electionConfiguration['nlp_election_cycle'];
    try {
      $query = $this->connection->select(self::NLP_RESULTS_TBL, 'r');
      $query->fields('r');
      $query->condition('active',TRUE);
      $query->condition('county',$county);
      $query->condition('cycle',$cycle);
      $result = $query->execute();
    }
    catch (Exception $e) {
      nlp_debug_msg('e', $e->getMessage() );
      return FALSE;
    }
    $voterStatus = array();
    do {
      $report = $result->fetchAssoc();
      if(!$report) {break;}
      $vanid = $report['vanid'];
      $voterStatus[$vanid]['mcid'] = $report['mcid'];
      $voterStatus[$vanid]['attempt'] = TRUE;
      if($report['type']==self::SURVEY) {
        $voterStatus[$vanid]['survey'] = TRUE;
      }
    } while (TRUE);
    $counts = array();
    foreach ($voterStatus as $status) {
      $mcid = $status['mcid'];
      if(empty($counts[$mcid]['attempts'])) {
        $counts[$mcid]['attempts'] = 1;
        $counts[$mcid]['contacts'] = 0;
      } else {
        $counts[$mcid]['attempts']++;
      }
      if(!empty($status['survey'])) {
        $counts[$mcid]['contacts']++;
      }
    }
    return $counts;
  }
  
  public function getNlVoterContactAttempts($mcid): int
  {
    //$config = $this->config('nlpservices.configuration');
    $electionConfiguration = $this->nlpConfig->get('nlpservices-election-configuration');
    $cycle = $electionConfiguration['nlp_election_cycle'];
    try {
      $query = $this->connection->select(self::NLP_RESULTS_TBL, 'r');
      $query->addField('r','vanid');
      $query->condition('mcid',$mcid);
      $query->condition('cycle',$cycle);
      $query->distinct();
      $br = $query->countQuery()->execute()->fetchField();
    }
    catch (Exception $e) {
      nlp_debug_msg('e', $e->getMessage() );
      return 0;
    }
    return $br;
  }
 
  public function getSurveyResponsesCountsById ($mcid,$qid): array
  {
    $counts = array();
    //$config = $this->config('nlpservices.configuration');
    $electionConfiguration = $this->nlpConfig->get('nlpservices-election-configuration');
    $cycle = $electionConfiguration['nlp_election_cycle'];
    try {
      $query = $this->connection->select(self::NLP_RESULTS_TBL, 'r');
      $query->fields('r');
      $query->condition('cycle',$cycle);
      $query->condition('type',self::SURVEY);
      $query->condition('qid',$qid);
      $query->condition('mcid',$mcid);
      $result = $query->execute();
    }
    catch (Exception $e) {
      nlp_debug_msg('e', $e->getMessage() );
      return array();
    }
    $vanIds = array();
    do {
      $voter = $result->fetchAssoc();
      if(empty($voter)) {break;}
      $vanid = $voter['vanid'];
      if(empty($vanIds[$vanid])) {
        $vanIds[$vanid] = array('vanid'=>$vanid,'contactDate'=>$voter['contactDate'],'rid'=>$voter['rid']);
      } else {
        if($voter['contactDate']>$vanIds[$vanid]['contactDate']) {
          $vanIds[$vanid] = array('vanid'=>$vanid,'contactDate'=>$voter['contactDate'],'rid'=>$voter['rid']);
        }
      }
    } while (TRUE);
    foreach ($vanIds as $response) {
      $rid = $response['rid'];
      if(!isset($counts[$rid])) {
        $counts[$rid] = 1;
      } else {
        $counts[$rid]++;
      }
    }
    return $counts;
  }
  
  public function getColumnNames(): array
  {
    try {
      $select = "SHOW COLUMNS FROM  {".self::NLP_RESULTS_TBL.'}';
      $result = $this->connection->query($select);
    }
    catch (Exception $e) {
      nlp_debug_msg('e', $e->getMessage() );
      return array();
    }
    $colNames = array();
    do {
      $name = $result->fetchAssoc();
      if(empty($name)) {break;}
      $colNames[] = $name['Field'];
    } while (TRUE);
    return $colNames;
  }
  
  public function getReportCount(): int
  {
    try {
      $query = $this->connection->select(self::NLP_RESULTS_TBL, 'r');
      $numRows = $query->countQuery()->execute()->fetchField();
    }
    catch (Exception $e) {
      nlp_debug_msg('e', $e->getMessage() );
      return 0;
    }
    return $numRows;
  }
  
  public function selectAllReports ($nextRecord): ?Database\StatementInterface
  {
    try {
      $query = $this->connection->select(self::NLP_RESULTS_TBL, 'r');
      $query->fields('r');
      $query->range($nextRecord, self::BATCH_LIMIT);
      $result = $query->execute();
    }
    catch (Exception $e) {
      nlp_debug_msg('e', $e->getMessage() );
      return NULL;
    }
    return $result;
  }
 
  public function decodeReportsHdr($fileHdr): array
  {
    $reportHdr = array_keys($this->reportFieldType);
    unset($reportHdr['reportIndex']);
    $hdrErr = array();
    $hdrPos = array();
    foreach ($reportHdr as $nlpKey => $importField) {
      $found = FALSE;
      foreach ($fileHdr as $fileCol=>$fileColName) {
        if(trim($fileColName) == $importField) {
          $hdrPos[$nlpKey] = $fileCol;
          $found = TRUE;
          break;
        }
      }
      if(!$found) {
        $hdrErr[] = 'The import column "'.$importField.'" is missing.';
      }
    }
    $fieldPos['pos'] = $hdrPos;
    $fieldPos['err'] = $hdrErr;
    $fieldPos['ok'] = empty($hdrErr);
    return $fieldPos;
  }
  
  public function prepareExportRecord($resultArray): array
  {
    $record = [];
    foreach ($resultArray as $key=>$recordField) {
      switch ($key) {
        case 'text':
          $sansNL = str_replace("\r\n", "<br>", $recordField);
          $record[$key] = $sansNL;
          break;
        default:
          $record[$key] = $recordField;
          break;
      }
    }
    return $record;
  }
  /*
  public function initializeBatch() {
    $header = array_keys($this->reportFieldType);
    //$hdr = $this->resultsList;
    unset($header[0]);  // reportIndex.
    $this->reportHdr = $hdr;
    $this->reportFields = implode(', ', $hdr);
    //nlp_debug_msg('reportFields',$this->reportFields);
  }
  */
  private function insertNlBatch()
  {
    $transaction = $this->connection->startTransaction();

    try {
      $query = $this->connection->insert(self::NLP_RESULTS_TBL)
          ->fields($this->reportFields);
      foreach ($this->records as $record) {
        unset($record['reportIndex']);
        $query->values($record);
      }
      $query->execute();
    }
    catch (Exception $e) {
      nlp_debug_msg('e', $e->getMessage() );
    }
    unset($transaction);
  }
  
  public function insertNlReports($report): bool
  {
    $record = [];
    foreach ($this->reportFields as $key) {
      if(!empty($report[$key]) AND $report[$key]!='NULL') {
        $record[$key] = $report[$key];
      } else {
        $record[$key] = NULL;
      }
    }
    $batchSubmit = FALSE;
    $this->records[$this->sqlCnt++] = $record;
    // When we reach 100 records, insert all of them in one query.
    if ($this->sqlCnt == self::MULTI_INSERT) {
      $this->sqlCnt = 0;
      $this->batchCnt++;
      $this->insertNlBatch();
      $this->records = array();
      if($this->batchCnt==self::BATCH) {
        $this->batchCnt=0;
        $batchSubmit = TRUE;
      }
    }
    return $batchSubmit;
  }
  
  public function flushNlReports() {
    if(empty($this->records)) {return;}
    $this->insertNlBatch();
    $this->records = array();
  }
  
  public function emptyNlTable() {
    try {
      $this->connection->truncate(self::NLP_RESULTS_TBL)->execute();
    }
    catch (Exception $e) {
      nlp_debug_msg('e', $e->getMessage() );
    }
  }
  
}
