<?php

namespace Drupal\nlpservices;

use Drupal;
use Drupal\Core\Database\Connection;
use Exception;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\Core\Lock\LockBackendInterface;
//use Drupal\nlpservices\NlpMatchbacks;
//use Drupal\nlpservices\NlpReports;


class NlpVoters {
  
  const VOTER_TBL = 'nlp_voter';
  const VOTER_TURF_TBL = 'nlp_voter_turf';
  const VOTER_STATUS_TBL = 'nlp_voter_status';
  const VOTER_ADDRESS_TBL = 'nlp_voter_address';

  private array $statusList = ['vanid','cycle','deceased','hostile','voter','voted',];
  
  public array $addressFields = ['streetNo','streetPrefix','streetName','streetType','streetSuffix','aptType','aptNo',
    'city'];

  private array $voterVanHdr = array(
    'vanid' => array('name'=>'Voter File VANID','err'=>'Voter File VANID','AC'=>FALSE, ),
    'hostile' => array('name'=>'NLP_Hostile_(Public)','err'=>'Activist Code - NLP_Hostile_(Public)','AC'=>TRUE, ),
  );
  
  protected Connection $connection;
  protected NlpMatchbacks $matchbacksObj;
  protected NlpReports $reportsObj;

  public function __construct( $connection, $matchbacksObj, $reportsObj) {
    $this->connection = $connection;
    $this->matchbacksObj = $matchbacksObj;
    $this->reportsObj = $reportsObj;
  }
  
  public static function create(ContainerInterface $container): NlpVoters
  {
    return new static(
      $container->get('database'),
      $container->get('nlpservices.matchbacks'),
      $container->get('nlpservices.reports'),
    );
  }
  
  public function fetchVanIdsByNl($mcid): array
  {
    try {
      $query = $this->connection->select(self::VOTER_TURF_TBL, 'g');
      //$query = db_select(self::VOTER_TURF_TBL, 'g');
      $query->addField('g','vanid');
      $query->condition('mcid',$mcid);
      $result = $query->execute();
    }
    catch (Exception $e) {
      nlp_debug_msg('e', $e->getMessage() );
      return array();
    }
    $voters = array();
    do {
      $dbVoter = $result->fetchAssoc();
      if(empty($dbVoter)) {break;}
      $voters[$dbVoter['vanid']] = $dbVoter['vanid'];
    } while (TRUE);
    return $voters;
  }
  
  public function getVoterCountByNl($mcid): int
  {
    try {
      $query = $this->connection->select(self::VOTER_TURF_TBL, 'g');
      $query->addField('g','vanid');
      $query->condition('mcid',$mcid);
      $voterCount = $query->countQuery()->execute()->fetchField();
    }
    catch (Exception $e) {
      nlp_debug_msg('e', $e->getMessage() );
      return 0;
    }
    return $voterCount;
  }
  
  public function duplicateVoters($vanIds) {
    $dupVoters = array();
    try {
      $query = $this->connection->select(self::VOTER_TURF_TBL, 'g');
      $query->fields('g');
      $query->condition('vanid',$vanIds,'IN');
      $result = $query->execute();
    }
    catch (Exception $e) {
      nlp_debug_msg('e', $e->getMessage() );
      return FALSE;
    }
    do {
      $dupVoter = $result->fetchAssoc();
      if(empty($dupVoter)) {break;}
      $dupVoters[$dupVoter['vanid']] = $dupVoter;
    } while (TRUE);
    return $dupVoters;
  }
  
  public function getVotersInList($vanIds) {
    try {
      $query = $this->connection->select(self::VOTER_TBL, 'v');
      $query->fields('v');
      $query->condition('vanid',$vanIds,'IN');
      $result = $query->execute();
    }
    catch (Exception $e) {
      nlp_debug_msg('e', $e->getMessage() );
      return FALSE;
    }
    $dupVoters = array();
    do {
      $dupVoter = $result->fetchAssoc();
      if(empty($dupVoter)) {break;}
      $dupVoters[$dupVoter['vanid']] = $dupVoter;
    } while (TRUE);
    return $dupVoters;
  }
  
  public function lockVoters(): LockBackendInterface
  {
    $locked = FALSE;
    $lock = Drupal::lock();
    do {
      if ($lock->acquire('nlp_turf_commit')) {
        $locked = TRUE;
      } else {
        $lock->wait('nlp_turf_commit');
      }
    } while (!$locked);
    return $lock;
  }
  
  public function unlockVoters() {
    $lock = Drupal::lock();
    $lock->release('nlp_turf_commit');
  }
  
  public function getActivistCodeNames(): array
  {
    $activistCodeNames = array();
    foreach ($this->voterVanHdr as $nlpKey => $field) {
      if($field['AC']) {
        $activistCodeNames[$nlpKey] = $nlpKey;
      }
    }
    return $activistCodeNames;
  }
  
  public function nullVoterStatus(): array
  {
    $null = array();
    foreach ($this->statusList as $key) {
      $null[$key] = NULL;
    }
    return $null;
  }
  
  public function getVoterStatus($vanid): array
  {
    try {
      $query = $this->connection->select(self::VOTER_STATUS_TBL, 's');
      $query->fields('s');
      $query->condition('vanid',$vanid);
      $result = $query->execute();
    }
    catch (Exception $e) {
      nlp_debug_msg('e', $e->getMessage() );
      return $this->nullVoterStatus();
    }
    $status = $result->fetchAssoc();
    if(empty($status)) {
      $status = $this->nullVoterStatus();
    }
    //nlp_debug_msg('$status',$status);
    if(empty($status['voted'])) {
      $mbDate = $this->matchbacksObj->getMatchbackDate($vanid);
      //nlp_debug_msg('$mbDate',$mbDate);
      if (!empty($mbDate)) {  // Voted!
        $status['voted'] = $mbDate;
        $this->setVoterStatus($vanid,$status);
      }
    }
    return $status;
  }
  
  public function setVoterStatus($vanid, $fields): bool
  {
    try {
      $fields['vanid'] = $vanid;
      $this->connection->merge(self::VOTER_STATUS_TBL)
        ->keys(array('vanid' => $vanid,))
        ->fields($fields)
        ->execute();
    }
    catch (Exception $e) {
      nlp_debug_msg('e', $e->getMessage() );
      return FALSE;
    }
    return TRUE;
  }
  
  public function createVoter($voter): bool
  {
    $vanid = $voter['vanid'];
    $dbAddress = $voter['address'];
    $dbAddress['vanid'] = $voter['vanid'];
    unset($voter['address']);
    try {
      $this->connection->merge(self::VOTER_TBL)
        ->keys(array('vanid' => $vanid))
        ->fields($voter)
        ->execute();
    }
    catch (Exception $e) {
      nlp_debug_msg('e', $e->getMessage() );
      return FALSE;
    }
    try {
      $this->connection->insert(self::VOTER_ADDRESS_TBL)
        ->fields($dbAddress)
        ->execute();
    }
    catch (Exception $e) {
      nlp_debug_msg('e', $e->getMessage() );
      return FALSE;
    }
    return TRUE;
  }
  
  public function createTurfVoter($turfVoter): bool
  {
    try {
      $this->connection->insert(self::VOTER_TURF_TBL)
        ->fields($turfVoter)
        ->execute();
    }
    catch (Exception $e) {
      nlp_debug_msg('e', $e->getMessage() );
      return FALSE;
    }
    return TRUE;
  }
  
  public function updateTurfVoter($turfVoter): bool
  {
    try {
      $this->connection->merge(self::VOTER_TURF_TBL)
        ->keys(array(
          'turfIndex' => $turfVoter['turfIndex'],
          'vanid' => $turfVoter['vanid'],
        ))
        ->fields($turfVoter)
        ->execute();
    }
    catch (Exception $e) {
      nlp_debug_msg('e', $e->getMessage() );
      return FALSE;
    }
    return TRUE;
  }

  public function getVotersInTurf($turfIndex): ?array
  {
    try {
      $query = $this->connection->select(self::VOTER_TURF_TBL, 't')
        ->fields('t')
        ->condition('turfIndex', $turfIndex);
      $result = $query->execute();
    }
    catch (Exception $e) {
      nlp_debug_msg('e', $e->getMessage() );
      return NULL;
    }
    $voters = array();
    do {
      $voter = $result->fetchAssoc();
      if(empty($voter)) {break;}
      $voters[$voter['vanid']] = $voter['vanid'];
    } while (TRUE);
    return $voters;
  }

  public function fetchVotersByTurf($turfIndex): array
  {
    try {
      $query = $this->connection->select(self::VOTER_TBL, 'v');
      $query->join(self::VOTER_TURF_TBL, 'g', 'g.vanid = v.vanid');
      $query->join(self::VOTER_ADDRESS_TBL, 'a', 'a.TurfIndex = g.TurfIndex AND g.vanid = a.vanid' );
      $query->fields('v');
      $query->condition('g.TurfIndex',$turfIndex);
      $query->orderBy('city');
      $query->orderBy('streetName');
      $query->orderBy('streetType');
      $query->orderBy('streetNo');
      $query->orderBy('aptType');
      $query->orderBy('aptNo');
      $query->orderBy('lastName');
      $query->orderBy('firstName');
      $result = $query->execute();
    }
    catch (Exception $e) {
      nlp_debug_msg('e', $e->getMessage() );
      return array();
    }
    $voters = array();
    do {
      $voter = $result->fetchAssoc();
      if(empty($voter)) {break;}
      $voter['turfVoter'] = $this->getTurfVoter($voter['vanid'],$turfIndex);
      $voter['address'] = $this->fetchVoterAddress($voter['vanid'],$turfIndex);
      $voters[$voter['vanid']] = $voter;
    } while (TRUE);
    return $voters;
  }

  public function getTurfVoter($vanid,$turfIndex): array
  {
    try {
      $query = $this->connection->select(self::VOTER_TURF_TBL, 'g');
      $query->fields('g');
      $query->condition('vanid',$vanid);
      $query->condition('turfIndex',$turfIndex);
      $result = $query->execute();
    }
    catch (Exception $e) {
      nlp_debug_msg('e', $e->getMessage() );
      return array();
    }
    return $result->fetchAssoc();
  }
  
  public function getVotersTurf($vanid,$cycle): array
  {
    try {
      $query = $this->connection->select(self::VOTER_TURF_TBL, 'i');
      $query->fields('i');
      $query->condition('vanid',$vanid);
      $query->condition('cycle',$cycle);
      $result = $query->execute();
      $dbTurf = $result->fetchAssoc();
    }
    catch (Exception $e) {
      nlp_debug_msg('e', $e->getMessage()  );
      return [];
    }
    if(empty($dbTurf)) {return [];}
    return $dbTurf;
  }

  public function deleteVotersInTurf($turfIndex) {
    $this->connection->delete(self::VOTER_TURF_TBL)
      ->condition('turfIndex', $turfIndex)
      ->execute();
  }
  
  public function deleteAddressesInTurf($turfIndex) {
    $this->connection->delete(self::VOTER_ADDRESS_TBL)
      ->condition('turfIndex', $turfIndex)
      ->execute();
  }
  
  public function updateTurfNote($turfIndex,$vanid,$note,$reportIndex,$noteId): bool
  {
    try {
      $this->connection->merge(self::VOTER_TURF_TBL)
        ->keys(array(
          'turfIndex' => $turfIndex,
          'vanid' => $vanid,
        ))
        ->fields(array(
          'reportIndex' => $reportIndex,
          'NoteId' => $noteId,
          'note' => $note,))
        ->execute();
    }
    catch (Exception $e) {
      nlp_debug_msg('e', $e->getMessage() );
      return FALSE;
    }
    return TRUE;
  }
  
  public function fetchVoterAddress($vanid,$turfIndex=NULL): array
  {
    try {
      $query = $this->connection->select(self::VOTER_ADDRESS_TBL, 'a');
      $query->fields('a');
      if(!empty($turfIndex)) {
        $query->condition('turfIndex',$turfIndex);
      }
      $query->condition('vanid',$vanid);
      $result = $query->execute();
    }
    catch (Exception $e) {
      nlp_debug_msg('e', $e->getMessage() );
      return [];
    }
    if(empty($address = $result->fetchAssoc())) {return [];}
    return $address;
  }
  
  public function addressCompare($address1,$address2): bool
  {
    if(empty($address2) OR empty($address1)) {return FALSE;}
    foreach ($this->addressFields as $addressKey) {
      if($address1[$addressKey] != $address2[$addressKey]) {
        return FALSE;
      }
    }
    return TRUE;
  }
  
  public function addressExtract($address): array
  {
    $simpleAddress = array();
    foreach ($this->addressFields as $addressKey) {
      $simpleAddress[$addressKey] = $address[$addressKey];
    }
    return $simpleAddress;
  }
  
  public function setMovedStatus($turfIndex,$vanid,$value) {
    try {
      $this->connection->update(self::VOTER_ADDRESS_TBL)
        ->fields(array(
          'moved' => $value,))
        ->condition('turfIndex',$turfIndex)
        ->condition('vanid',$vanid)
        ->execute();
    }
    catch (Exception $e) {
      nlp_debug_msg('e', $e->getMessage() );
      return NULL;
    }
  }
  
  public function updateVoterPhone($vanid,$phone)
  {
    try {
      $this->connection->merge(self::VOTER_TBL)
        ->keys(array('vanid' => $vanid))
        ->fields($phone)
        ->execute();
    }
    catch (Exception $e) {
      nlp_debug_msg('e', $e->getMessage() );
      return;
    }
  }
  
  public function getVoterById($vanid,$turfIndex=NULL): array
  {
    try {
      $query = $this->connection->select(self::VOTER_TBL, 'v');
      $query->fields('v');
      $query->condition('vanid',$vanid);
      $result = $query->execute();
    }
    catch (Exception $e) {
      nlp_debug_msg('e', $e->getMessage() );
      return [];
    }
    $voter = $result->fetchAssoc();
    if(empty($voter)) {return [];}  // voter not known.
    $voter['address'] = $this->fetchVoterAddress($vanid,$turfIndex);
    return $voter;
  }
  
  public function getVoterCd($vanid) {
    $address = $this->fetchVoterAddress($vanid,NULL);
    return $address['cd'];
  }

  public function getVoted($county): int
  {
    try {
      $query = $this->connection->select(self::VOTER_TURF_TBL, 'g');
      $query->join($this->matchbacksObj::MATCHBACK_TBL, 'm', 'g.vanid = m.vanid');
      $query->fields('g', array('vanid', 'county'));
      $query->condition('g.County',$county);
      $query->isNotNull($this->matchbacksObj::DATE);
      $query->distinct();
      $votedCount = $query->countQuery()->execute()->fetchField();
    }
    catch (Exception $e) {
      nlp_debug_msg('e', $e->getMessage() );
      return 0;
    }
    return $votedCount;
  }

  public function getVoterCount($county): int
  {
    try {
      $query = $this->connection->select(self::VOTER_TURF_TBL, 'g');
      $query->addField('g','vanid');
      $query->distinct();
      if(!empty($county)) {
        $query->condition('county',$county);
      }
      $voterCount = $query->countQuery()->execute()->fetchField();
    }
    catch (Exception $e) {
      nlp_debug_msg('e', $e->getMessage() );
      return 0;
    }
    return $voterCount;
  }

  public function getVotedAndContacted($county): int
  {
    try {
      $query = $this->connection->select(self::VOTER_TURF_TBL, 'g');
      $query->join($this->matchbacksObj::MATCHBACK_TBL, 'm', 'g.vanid = m.vanid AND g.County = :county',
        array(':county' => $county));
      $query->addField('g','vanid');
      $query->isNotNull('m.'.$this->matchbacksObj::DATE);
      $query->distinct();
      $voted = $query->execute();
    }
    catch (Exception $e) {
      nlp_debug_msg('e', $e->getMessage() );
      return 0;
    }
    $contactedCount = 0;
    do {
      $voter = $voted->fetchAssoc();
      if(empty($voter)) {break;}
      $voterContacted = $this->reportsObj->voterContacted($voter['vanid']);
      if($voterContacted) {$contactedCount++;}
    } while (TRUE);
    return $contactedCount;
  }

  public function getVotedAndAttempted($county): int
  {
    try {
      $query = $this->connection->select(self::VOTER_TURF_TBL, 'g');
      $query->join($this->matchbacksObj::MATCHBACK_TBL, 'm', 'g.vanid = m.vanid AND g.County = :county',
        array(':county' => $county));
      $query->addField('g','vanid');
      $query->isNotNull('m.'.$this->matchbacksObj::DATE);
      $query->distinct();
      $voted = $query->execute();
    }
    catch (Exception $e) {
      nlp_debug_msg('e', $e->getMessage() );
      return 0;
    }
    $contactedCount = 0;
    do {
      $voter = $voted->fetchAssoc();
      if(empty($voter)) {break;}
      $voterContacted = $this->reportsObj->voterContactAttempted($voter['vanid']);
      if($voterContacted) {$contactedCount++;}
    } while (TRUE);
    return $contactedCount;
  }

  public function postcardAndVoted($county): int
  {
    try {
      $query = $this->connection->select(self::VOTER_TURF_TBL, 'g');
      $query->join($this->matchbacksObj::MATCHBACK_TBL, 'm', 'g.vanid = m.vanid AND g.County = :county',
        array(':county' => $county));
      $query->addField('g','vanid');
      $query->isNotNull('m.'.$this->matchbacksObj::DATE);
      $query->distinct();
      $voted = $query->execute();
    }
    catch (Exception $e) {
      nlp_debug_msg('e', $e->getMessage() );
      return 0;
    }
    $contactedCount = 0;
    do {
      $voter = $voted->fetchAssoc();
      if(empty($voter)) {break;}
      $voterContacted = $this->reportsObj->voterSentPostcard($voter['vanid']);
      if($voterContacted) {$contactedCount++;}
    } while (TRUE);
    return $contactedCount;
  }

  public function getParticipatingCounties() {

    try {
      $query = $this->connection->select(self::VOTER_TURF_TBL, 'g');
      $query->addField('g', 'county');
      $query->distinct();
      $query->orderBy('county');
      $result = $query->execute();
    }
    catch (Exception $e) {
      nlp_debug_msg('e', $e->getMessage() );
      return FALSE;
    }

    $countyNames = array();
    do {
      $record = $result->fetchAssoc();
      if(empty($record)) {break;}
      if(!empty($record['county'])) {
        $countyNames[] = $record['county'];
      }
    } while (TRUE);
    return $countyNames;
  }

  public function getTurfMcid($vanid,$turfIndex) {
    try {
      $query = $this->connection->select(self::VOTER_TURF_TBL, 'v');
      $query->addField('v','mcid');
      $query->condition('turfIndex',$turfIndex);
      $query->condition('vanid',$vanid);
      $result = $query->execute();
    }
    catch (Exception $e) {
      nlp_debug_msg('e', $e->getMessage() );
      return NULL;
    }
    $mcidRecord = $result->fetchAssoc();
    if(empty($mcidRecord)) {return NULL;}
    return $mcidRecord['mcid'];
  }

  public function getVoterAddresses($vanid) {
    try {
      $query = $this->connection->select(self::VOTER_ADDRESS_TBL, 'a');
      $query->fields('a');
      $query->condition('vanid',$vanid);
      $result = $query->execute();
    }
    catch (Exception $e) {
      nlp_debug_msg('e', $e->getMessage() );
      return FALSE;
    }
    $addresses = array();
    do {
      $address = $result->fetchAssoc();
      if(empty($address)) {break;}
      $addresses[] = $address;
    } while (TRUE);
    return $addresses;
  }

  /** @noinspection PhpUnused */
  public function getNlId($vanid): array
  {
    try {
      $query = $this->connection->select(self::VOTER_TURF_TBL, 't');
      $query->fields('t');
      $query->condition('vanid',$vanid);
      $result = $query->execute();
    }
    catch (Exception $e) {
      nlp_debug_msg('e', $e->getMessage() );
      return array();
    }
    $mcids = array();
    do {
      $nl = $result->fetchAssoc();
      if(empty($nl)) {break;}
      $mcids[$nl['mcid']][$nl['turfIndex']] = $nl['turfIndex'];
    } while (TRUE);
    return $mcids;
  }
  
  public function searchVoters($county,$needles): array
  {
    //nlp_debug_msg('$county',strToHex($county));
    //nlp_debug_msg('$needle',strToHex($needle));
    try {
      $query = $this->connection->select(self::VOTER_TBL, 'v');
      $query->join(self::VOTER_TURF_TBL, 'g', 'g.vanid = v.vanid');
  
      $query->fields('v');
      $query->addField('g','county');
      $query->condition('county',$county);
      /*
      $orGroup = $query->orConditionGroup()
        ->condition('lastName', "%" . $query->escapeLike($needles['lastName']) . "%", 'LIKE')
        ->condition('firstName', "%" . $query->escapeLike($needles['firstName']) . "%", 'LIKE');
      $query->condition($orGroup);
      */
      //nlp_debug_msg('$needles',$needles);
      //nlp_debug_msg('lastName',strToHex($needles['lastName']));
      if(!empty($needles['lastName'])) {
        $query->condition('lastName', "%" . $query->escapeLike($needles['lastName']) . "%", 'LIKE');
      }
  
      //$query->condition('lastName', "%" . $query->escapeLike($needles['lastName']) . "%", 'LIKE');
      //$query->condition('firstName', "%" . $query->escapeLike($needles['firstName']) . "%", 'LIKE');
      
      if(!empty($needles['firstName'])) {
        $orGroup = $query->orConditionGroup()
          ->condition('firstName', "%" . $query->escapeLike($needles['firstName']) . "%", 'LIKE')
          ->condition('nickname', "%" . $query->escapeLike($needles['firstName']) . "%", 'LIKE');
        $query->condition($orGroup);
      }
      
      $query->orderBy('lastName');
      $result =  $query->execute();
    }
    catch (Exception $e) {
      nlp_debug_msg('e', $e->getMessage() );
      return [];
    }
    // Fetch each NL record and build the array of information about each NL
    // needed to build the display table.
    $voterRecords = [];
    do {
      $voterRecord = $result->fetchAssoc();
      //nlp_debug_msg('$voterRecords',$voterRecords);
      if(empty($voterRecord)) {break;}
      $vanid = $voterRecord['vanid'];
      $voterRecords[$vanid] = $voterRecord;
    } while (TRUE);
    return $voterRecords;
  }

}
