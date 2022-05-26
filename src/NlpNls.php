<?php

namespace Drupal\nlpservices;

use Drupal;
use Drupal\Core\Database\Connection;
use Exception;
use Symfony\Component\DependencyInjection\ContainerInterface;
//use Drupal\nlpservices\NlpAwards;

class NlpNls {
  
  const NLS_TBL = 'nlp_nls';
  const NLS_GRP_TBL = 'nlp_nls_group';
  const NLS_STATUS_TBL = 'nlp_nls_status';
  //const NLS_STATUS_HISTORY_TBL = 'nlp_nls_status_history';
  
  const CANVASS = 'canvass';
  const MINIVAN = 'minivan';
  const PHONE = 'phone';
  const MAIL = 'mail';
  
  const NOTES_MAX = '75';   // Notes max length of the note.
  //const NOTES_WRAP = '25';  // Notes max length for single line.
  
  public array $contactList = array(self::CANVASS,self::MINIVAN,self::PHONE,self::MAIL,);
  
  const DASH = '-';
  const ASKED = 'asked';
  const YES = 'yes';
  const NO = 'no';
  const QUIT = 'quit';
  
  public array $askList = array(self::DASH,self::ASKED,self::YES,self::NO,self::QUIT);

  private array $statusList = array('mcid','county','loginDate','contact','signedUp','turfCut','turfDelivered',
    'resultsReported','awardPending','preferredContactMethod','asked','notes','userName',);

  protected Connection $connection;
  protected NlpAwards $awardsObj;

  public function __construct( $connection, $awardsObj) {
    $this->connection = $connection;
    $this->awardsObj = $awardsObj;
  }
  
  public static function create(ContainerInterface $container): NlpNls
  {
    return new static(
      $container->get('database'),
      $container->get('nlpservices.awards'),
    );
  }

  public function deleteNlGrp($county) {
    try {
      $this->connection->delete(self::NLS_GRP_TBL)
        ->condition('county', $county)
        ->execute();
    }
    catch (Exception $e) {
      nlp_debug_msg('e', $e->getMessage() );
      return;
    }
  }
  
  public function deleteNls($county) {
    try {
      $this->connection->delete(self::NLS_TBL)
        ->condition('county', $county)
        ->execute();
    }
    catch (Exception $e) {
      nlp_debug_msg('e', $e->getMessage() );
      return;
    }
  }

  public function deleteNl($mcid) {
    $this->connection->delete(self::NLS_TBL)
      ->condition('mcid', $mcid)
      ->execute();
  }

  public function createNl($nlRecord): bool
  {
    $nlRecord['active'] = TRUE;
    $this->deleteNl($nlRecord['mcid']);
    try {
      //db_insert(self::NLS_TBL)
      $this->connection->insert(self::NLS_TBL)
        ->fields($nlRecord)
        ->execute();
    }
    catch (Exception $e) {
      nlp_debug_msg('error', $e->getMessage() );
      return FALSE;
    }
    return TRUE;
  }

  /** @noinspection PhpUnused */
  /* used in batch function */
  public function createNlGrp($mcid, $county) {
    try {
      //db_insert(self::NLS_GRP_TBL)
      $this->connection->insert(self::NLS_GRP_TBL)
        ->fields(array(
          'mcid' => $mcid,
          'county' => $county,
        ))
        ->execute();
    }
    catch (Exception $e) {
      nlp_debug_msg('error', $e->getMessage() );
    }
  }

  public function getNlsStatus($mcid,$county): array
  {
    try {
      $query = $this->connection->select(self::NLS_STATUS_TBL, 's');
      $query->fields('s');
      $query->condition('county',$county);
      $query->condition('mcid',$mcid);
      $result = $query->execute();
    }
    catch (Exception $e) {
      nlp_debug_msg('e', $e->getMessage() );
      return [];
    }
    $nlStatus = $result->fetchAssoc();
    if(empty($nlStatus)) {
      $nlStatus = array();
      //$nlpKeys = array_keys($this->statusList);
      foreach ($this->statusList as $nlpKey) {
        $nlStatus[$nlpKey] = NULL;
      }
      $nlStatus['mcid'] = $mcid;
      $nlStatus['county'] = $county;
      $nlStatus['contact'] = self::CANVASS;
      $nlStatus['asked'] = self::DASH;
    }
    return $nlStatus;
  }
  
  public function setNlsStatus($status): bool
  {
    try {
      $this->connection->merge(self::NLS_STATUS_TBL)
        ->fields($status)
        ->keys(array(
          'mcid' => $status['mcid'],
          'county' => $status['county']))
        ->execute();
    }
    catch (Exception $e) {
      nlp_debug_msg('e', $e->getMessage() );
      return FALSE;
    }
    return TRUE;
  }
  
  public function getNls($county,$hd): array
  {
    try {
      $query = $this->connection->select(self::NLS_TBL, 'n');
      $query->fields('n');
      $query->join(self::NLS_GRP_TBL, 'g', 'n.mcid = g.mcid');
      $query->fields('g');
      $query->condition('g.county',$county);
      //$query->condition('n.hd',$hd);

      if ($hd != 'ALL') {
        $query->condition('n.hd',$hd);
      }

      $query->orderBy('hd');
      $query->orderBy('lastName');
      $query->orderBy('nickname');
      //nlp_debug_msg('$query',$query);
      $result =  $query->execute();
    }
    catch (Exception $e) {
      nlp_debug_msg('e', $e->getMessage() );
      return [];
    }
    // Fetch each NL record and build the array of information about each NL
    // needed to build the display table.
    $nlRecords = array();
    do {
      $nlRecord = $result->fetchAssoc();
      if(empty($nlRecord)) {break;}
      $mcid = $nlRecord['mcid'];
      $nlRecord['status'] = $this->getNlsStatus($mcid,$county);
      $nlRecords[$mcid] = $nlRecord;
    } while (TRUE);
    return $nlRecords;
  }

  public function getHdList($county) {
    // Get the list of distinct HD numbers for this group, order numerically.
    //nlp_debug_msg('$county',$county);
    try {
      $query = $this->connection->select(self::NLS_GRP_TBL, 'g');
      $query->join(self::NLS_TBL, 'n', 'g.mcid = n.mcid');
      $query->addField('n', 'hd');
      $query->distinct();
      $query->condition('g.county',$county);
      $query->orderBy('hd');
      $result = $query->execute();
    }
    catch (Exception $e) {
      nlp_debug_msg('e', $e->getMessage() );
      return FALSE;
    }
    
    $hdOptions  = array();
    do {
      $hdRecord = $result->fetchAssoc();
      //nlp_debug_msg('$hdRecord',$hdRecord);
      if(empty($hdRecord)) {break;}
      $hdOptions[$hdRecord['hd']] = $hdRecord['hd'];
    } while (TRUE);
    return $hdOptions;
  }
  
  public function getPctList($county,$hd) {
    // Get the list of precinct numbers with at least one prospective NL in
    // this HD, order numerically by precinct number.
    //nlp_debug_msg('$county',$county);
    //nlp_debug_msg('$hd',$hd);
  
    try {
      $query = $this->connection->select(self::NLS_GRP_TBL, 'g');
      $query->join(self::NLS_TBL, 'n', 'g.mcid = n.mcid');
      $query->addField('n', 'precinct');
      $query->distinct();
      $query->condition('g.county',$county);
      $query->condition('hd',$hd);
      $query->orderBy('precinct');
      $result = $query->execute();
    }
    catch (Exception $e) {
      nlp_debug_msg('e', $e->getMessage()  );
      return FALSE;
    }
    
    $pctOptions = array();
    do {
      $pct = $result->fetchAssoc();
      if(empty($pct)) {break;}
      $pctOptions[] = $pct['precinct'];
    } while (TRUE);
    return $pctOptions;
  }
  
  function getNlList($county,$pct) {
    // Get a list of the NLs in the selected precinct, order by name.
    
    try {
      $query = $this->connection->select(self::NLS_GRP_TBL, 'g');
      $query->join(self::NLS_TBL, 'n', 'g.mcid = n.mcid');
      $query->addField('n', 'nickname');
      $query->addField('n', 'lastName');
      $query->addField('n', 'email');
      $query->addField('n', 'phone');
      $query->addField('n', 'mcid');
      $query->condition('precinct',$pct);
      $query->condition('g.county',$county);
      $query->orderBy('lastName');
      $query->orderBy('nickname');
      $result = $query->execute();
    }
    catch (Exception $e) {
      nlp_debug_msg('e', $e->getMessage()  );
      return FALSE;
    }
    $nlMcid = $nlOptions = array();
    do {
      $nl = $result->fetchAssoc();
      if(empty($nl)) {break;}
      $nlOptions[$nl['mcid']] = $nl['nickname'].' '.$nl['lastName'].': '.$nl['email'].', mcid['.$nl['mcid'].']';
      $nlMcid[$nl['mcid']] = $nl;
    } while (TRUE);
    return array('options'=>$nlOptions,'mcidArray'=>$nlMcid);
  }
  
  public function getNlById($mcid): array
  {
    try {
      $query = $this->connection->select(self::NLS_TBL, 'n');
      $query->fields('n');
      $query->condition('mcid',$mcid);
      $result = $query->execute();
    }
    catch (Exception $e) {
      nlp_debug_msg('e', $e->getMessage() );
      return [];
    }
    $nlRecord = $result->fetchAssoc();
    //nlp_debug_msg('$nlRecord',$nlRecord);
    if(empty($nlRecord)) return [];
    return $nlRecord;
  }
  
  public function getCountyNls($county): array
  {
    try {
      $query = $this->connection->select(self::NLS_GRP_TBL, 'g');
      $query->join(self::NLS_TBL, 'n', 'g.mcid = n.mcid');
      $query->join(self::NLS_STATUS_TBL, 's', 'g.mcid = s.mcid');
      $query->addField('g', 'mcid');
      $query->addField('g', 'county');
      $query->addField('s', 'resultsReported');
      $query->addField('s', 'signedUp');
      $query->addField('s', 'loginDate');
      $query->addField('n', 'hd');
      $query->addField('n', 'precinct');
      $query->addField('n', 'nickname');
      $query->addField('n', 'lastName');
      $query->addField('n', 'firstName');
      $query->addField('n', 'phone');
      $query->addField('n', 'email');
      $query->condition('g.'.'county',$county);
      $result = $query->execute();
    }
    catch (Exception $e) {
      nlp_debug_msg('e', $e->getMessage() );
      return array();
    }
    $nlRecords = array();
    do {
      $nlRecord = $result->fetchAssoc();
      //nlp_debug_msg('$nlRecord',$nlRecord);
      if(empty($nlRecord)) {break;}
      $nlRecords[$nlRecord['mcid']] = $nlRecord;
    } while (TRUE);
    return $nlRecords;
  }
  
  public function resultsReported($mcid,$county): bool
  {
    try {
      $query = $this->connection->select(self::NLS_STATUS_TBL, 's');
      $query->addField('s', 'resultsReported');
      $query->condition('mcid',$mcid);
      $result = $query->execute();
    }
    catch (Exception $e) {
      nlp_debug_msg('e', $e->getMessage() );
      return FALSE;
    }
    $resultsReported = $result->fetchAssoc();
    if(!empty($resultsReported['resultsReported'])) {
      return TRUE;
    }
    try {
      $this->connection->update(self::NLS_STATUS_TBL)
        ->fields(array('resultsReported' => 'Y',))
        ->condition('mcid',$mcid)
        ->condition('county',$county)
        ->execute();
    }
    catch (Exception $e) {
      nlp_debug_msg('e', $e->getMessage() );
      return FALSE;
    }

    $config = Drupal::config('nlpservices.configuration');
    $electionDates = $config->get('nlpservices-election-configuration');
    $cycle = $electionDates['nlp_election_cycle'];
    $this->awardsObj->awardsLevelUp($mcid,$cycle);

    return TRUE;
  }

  public function getDistrictsForNls($nlsWithTurfs): array
  {
    $turfsByDistrict = [];
    $mcids = array_keys($nlsWithTurfs);
    foreach ($mcids as $mcid) {
      try {
        $query = $this->connection->select(self::NLS_TBL, 'n');
        $query->addField('n','hd');
        $query->addField('n','precinct');
        $query->addField('n','email');
        //$query->fields('n');
        $query->condition('mcid',$mcid);
        $result = $query->execute();
      }
      catch (Exception $e) {
        nlp_debug_msg('getDistrictsForNls', $e->getMessage() );
        return array();
      }
      do {
        $nlRecord = $result->fetchAssoc();
        if(empty($nlRecord)) {break;}
        //$mcid = $nlRecord['mcid'];
        foreach ($nlsWithTurfs[$mcid] as $turfIndex => $turf) {
          $nlsWithTurfs[$mcid][$turfIndex]['email'] = $nlRecord['email'];
        }
        $turfsByDistrict[$nlRecord['hd']][$nlRecord['precinct']][$mcid] = $nlsWithTurfs[$mcid];
      } while (TRUE);
    }
    return $turfsByDistrict;
  }

  public function updateNlStatus($mcid,$key) {
    $this->connection->update(self::NLS_STATUS_TBL)
      ->fields(array(
        $key => 'Y',))
      ->condition('mcid',$mcid)
      ->execute();
  }

  public function searchNls($county,$needle): array
  {
    //nlp_debug_msg('$county',strToHex($county));
    //nlp_debug_msg('$needle',strToHex($needle));
    try {
      $query = $this->connection->select(self::NLS_TBL, 'n');
      $query->fields('n');
      $query->condition('county',$county);
      $orGroup = $query->orConditionGroup()
        ->condition('lastName', "%" . $query->escapeLike($needle) . "%", 'LIKE')
        ->condition('firstName', "%" . $query->escapeLike($needle) . "%", 'LIKE')
        ->condition('nickname', "%" . $query->escapeLike($needle) . "%", 'LIKE')
        ->condition('email', "%" . $query->escapeLike($needle) . "%", 'LIKE')
        ->condition('mcid', "%" . $query->escapeLike($needle) . "%", 'LIKE');
      $query->condition($orGroup);
      $query->orderBy('lastName');
      $query->orderBy('nickname');
      $result =  $query->execute();
    }
    catch (Exception $e) {
      nlp_debug_msg('e', $e->getMessage() );
      return [];
    }
    // Fetch each NL record and build the array of information about each NL
    // needed to build the display table.
    $nlRecords = [];
    do {
      $nlRecord = $result->fetchAssoc();
      //nlp_debug_msg('$nlRecord',$nlRecord);
      if(empty($nlRecord)) {break;}
      $mcid = $nlRecord['mcid'];
      $nlRecord['status'] = $this->getNlsStatus($mcid,$county);
      $nlRecords[$mcid] = $nlRecord;
    } while (TRUE);
    return $nlRecords;
  }
}
