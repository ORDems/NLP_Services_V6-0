<?php

namespace Drupal\nlpservices;

use Drupal\Core\Database\Connection;
use Exception;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\Core\Database\Transaction;

class NlpMatchbacks
{
  const MATCHBACK_TBL = 'nlp_matchback';

  const DATE = 'date';

  const MULTI_INSERT = 100;
  const BATCH = 100;

  private array $records = array();
  private int $sqlCnt = 0;
  private int $batchCnt = 0;
  
  private array $fields = array('vanid','date');
  
  private array $matchbackVanHdr = array(
    'vanid' => array('name'=>'VoterFileVANID','err'=>'Voter File VANID'),
    'ballotReceived' => array('name'=>'BallotReceived','err'=>'BallotReceived'),
    'earlyVoted' => array('name'=>'EarlyVoted','err'=>'BallotReceived'),
  );
  
  protected Connection $connection;

  public function __construct(  $connection) {
    $this->connection = $connection;
  }
  
  public static function create(ContainerInterface $container): NlpMatchbacks
  {
    return new static(
      $container->get('database'),
    );
  }
  
  public function decodeMatchbackHdr($fileHdr): array
  {
    $hdrErr = array();
    $hdrPos = array();
    foreach ($this->matchbackVanHdr as $nlpKey => $vanField) {
      foreach ($fileHdr as $fileCol=>$fileColName) {
        //nlp_debug_msg('$fileColName',strToHex($fileColName));
        $fileColName = preg_replace('/[^A-Za-z0-9\-]/', '', $fileColName);
        //nlp_debug_msg('$fileColName',strToHex($fileColName));
        //nlp_debug_msg('name',strToHex($vanField['name']));
        if(trim($fileColName) == $vanField['name']) {
          //nlp_debug_msg('$fileCol',$fileCol);
          $hdrPos[$nlpKey] = $fileCol;
          break;
        }
      }
    }
    $fieldPos['pos'] = $hdrPos;
    $fieldPos['err'] = $hdrErr;
    $fieldPos['ok'] = empty($hdrErr);
    return $fieldPos;
  }

  /** @noinspection PhpUnused */
  public function insertMatchbacks($vanid, $date): bool
  {
    $record = array(
      'vanid' => $vanid,
      'date' => $date,
    );
    $batchSubmit = FALSE;
    $this->records[$this->sqlCnt++] = $record;
    // When we reach 100 records, insert all of them in one query.
    if ($this->sqlCnt == self::MULTI_INSERT) {
      $this->sqlCnt = 0;
      $this->batchCnt++;
      $query = $this->connection->insert(self::MATCHBACK_TBL)
        ->fields($this->fields);
      foreach ($this->records as $record) {
        $query->values($record);
      }
      try {
        $query->execute();
      } catch (Exception $e) {
        nlp_debug_msg('Insert Matchback fail.',$e->getMessage());
        return FALSE;
      }
      $this->records = array();
      if($this->batchCnt==self::BATCH) {
        $batchSubmit = TRUE;
      }
    }
    return $batchSubmit;
  }

  /** @noinspection PhpUnused */
  public function flushMatchbacks(): bool
  {
    if(empty($this->records)) {return TRUE;}
    $query = $this->connection->insert(self::MATCHBACK_TBL)
      ->fields($this->fields);
    foreach ($this->records as $record) {
      $query->values($record);
    }
    try {
      $query->execute();
    } catch (Exception $e) {
      nlp_debug_msg('Insert Matchback fail.',$e->getMessage());
      return FALSE;
    }
    $this->records = array();
    return TRUE;
  }
  
  public function matchbackExists($vanid): bool
  {
    try {
      $query = $this->connection->select(self::MATCHBACK_TBL, 'm');
      $query->fields('m');
      $query->condition('vanid',$vanid);
      $result = $query->execute();
    }
    catch (Exception $e) {
      nlp_debug_msg('e', $e->getMessage() );
      return FALSE;
    }
    $exists = $result->fetchAssoc();
    return !empty($exists);
  }

  public function getMatchbackDate($vanid) {
    $query = $this->connection->select(self::MATCHBACK_TBL, 'm');
    $query->fields('m');
    $query->condition('vanid',$vanid);
    $result = $query->execute();
    $matchback = $result->fetchAssoc();
    if(empty($matchback)) {return NULL;}
    return $matchback['date'];
  }

  /** @noinspection PhpUnused */
  public function setLatestMatchbackDate($date) {
    try {
      $this->connection->merge(self::MATCHBACK_TBL)
        ->keys(['vanid' => 0,])
        ->fields(['date' => $date,])
        ->execute();
    } catch (Exception $e) {
      nlp_debug_msg('Merge Matchback fail.',$e->getMessage());
    }
  }

  /** @noinspection PhpUnused */
  public function getLatestMatchbackDate() {
    $query = $this->connection->select(self::MATCHBACK_TBL, 'm');
    $query->fields('m');
    $query->condition('vanid',0);
    $result = $query->execute();

    $lastDate = $result->fetchAssoc();
    if(!$lastDate) {return NULL;}
    return $lastDate['date'];
  }

  /** @noinspection PhpUnused */
  public function matchbackTransaction(): Transaction
  {
    return  $this->connection->startTransaction();
  }

}
