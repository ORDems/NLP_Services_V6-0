<?php

namespace Drupal\nlpservices;

use Drupal\Core\Database\Connection;
//use stdClass;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Exception;

class NlpAwards
{
  const AWARDS_TBL = 'nlp_awards';
  private array $awardsHeader = ['mcid','nickname','lastName','electionCount','participation'];
  
  protected Connection $connection;

  public function __construct(  $connection) {
    $this->connection = $connection;
  }
  
  public static function create(ContainerInterface $container): NlpAwards
  {
    return new static(
      $container->get('database'),
    );
  }
  
  public function mergeAward($award): bool
  {
    $award['participation'] = json_encode($award['participation']);
    try {
      $this->connection->merge(self::AWARDS_TBL)
        ->keys(array('mcid' => $award['mcid']))
        ->fields($award)
        ->execute();
    } catch (Exception $e) {
      nlp_debug_msg('e', $e->getMessage() );
      return FALSE;
    }
    return TRUE;
  }

  private function fetchAwardRecord($mcid): array
  {
    try {
      $query = $this->connection->select(self::AWARDS_TBL, 'n');
      $query->condition('mcid',$mcid);
      $query->fields('n');
      $result = $query->execute();
    } catch (Exception $e) {
      nlp_debug_msg('e', $e->getMessage() );
      return [];
    }
    if(!$award = $result->fetchAssoc()) {return [];}
    return $award;
  }
  
  public function getAward($mcid): array
  {
    $award = $this->fetchAwardRecord($mcid);
    if(empty($award)) {return [];}
    $award['participation'] = json_decode($award['participation']);
    return $award;
  }

  /** @noinspection PhpUnused */
  /*
  public function awardsLevelUp($mcid, $cycle): array
  {
    $participationObj = new stdClass();
    $cycleObj = new stdClass;
    $participationObj->$cycle = $cycleObj;
  
    $award = $this->fetchAwardRecord($mcid);

    if (empty($award)) {
      $award['participation'] = [];
    } else {
      $award['participation'] = json_decode($award['participation']);
    }
    $award['electionCount']++;

    if(empty($award['participation']->$cycle)) {
      if(empty($award['participation'])) {
        $award['participation'] = $participationObj;
      } else {
        $award['participation']->$cycle = $participationObj->$cycle;
      }
    }
    $award['participation']->$cycle->report = true;
    $this->mergeAward($award);
    return $award;
  }
*/
  public function awardsLevelUp($mcid,$cycle): array
  {
    $award = $this->fetchAwardRecord($mcid);
    if (empty($award)) {
      $participationObj = (object) [$cycle=>TRUE,];
      $award['mcid'] = $mcid;
      $award['participation'] = $participationObj;
      $award['electionCount'] = 1;
    } else {
      $participationObj = (object) json_decode($award['participation']);
      $participationObj->$cycle=TRUE;
      $award['participation'] = $participationObj;
      $award['electionCount']++;
    }
    $this->mergeAward($award);
    return $award;
  }

  public function getColumnNames(): array
  {
    try {
      $select = "SHOW COLUMNS FROM  {".self::AWARDS_TBL.'}';
      $result = $this->connection->query($select);
    }
    catch (Exception $e) {
      nlp_debug_msg('e', $e->getMessage() );
      return array();
    }
    $colNames = [];
    do {
      $name = $result->fetchAssoc();
      if(empty($name)) {break;}
      $colNames[] = $name['Field'];
    } while (TRUE);
    return $colNames;
  }

  public function getNlList(): array
  {
    try {
      $query = $this->connection->select(self::AWARDS_TBL, 'n');
      $query->addField('n','mcid');
      $result = $query->execute();
    } catch (Exception $e) {
      nlp_debug_msg('e', $e->getMessage() );
      return [];
    }
    if(!$awardList = $result->fetchAssoc()) {return [];}
    $list =[];
    do {
      $nl = $result->fetchAssoc();
      if(empty($nl)) {break;}
      $list[] = $nl['mcid'];
    } while (TRUE);
    return $list;
  }

  public function decodeAwardHeader($fileHdr): array
  {
    $hdrErr = $hdrPos =  [];
    foreach ($this->awardsHeader as $columnName) {
      $found = FALSE;
      foreach ($fileHdr as $fileCol=>$fileColName) {
        if(trim($fileColName) == $columnName) {
          $hdrPos[$columnName] = $fileCol;
          $found = TRUE;
          break;
        }
      }
      if(!$found) {
        $hdrErr[] = 'The awards column "'.$columnName.'" is missing.';
      }
    }
    $fieldPos['pos'] = $hdrPos;
    $fieldPos['err'] = $hdrErr;
    $fieldPos['ok'] = empty($hdrErr);
    return $fieldPos;
  }



}
