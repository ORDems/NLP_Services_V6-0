<?php

namespace Drupal\nlpservices;

use Drupal\Core\Database\Connection;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Exception;

class NlpCrosstabCounts
{
  protected Connection $connection;

  public function __construct( $connection) {
    $this->connection = $connection;
  }

  public static function create(ContainerInterface $container): NlpCrosstabCounts
  {
    return new static(
      $container->get('database'),
    );
  }

  const CROSSTAB_COUNTS_TBL = 'nlp_ballot_count';

  private array $crosstabCountVanHdr1 = [
    'county' => 'County',
    'party' => 'Party',
    'voteDate' => 'Vote Return Date',
    'total' => 'Total People',
    ];

  private array $crosstabCountVanHdr2 = [
    'balRet' => 'Bal Ret',
    ];

  public function decodeCrosstabsHeader($fileHdr,$fileHdr2): array
  {
    $vanField['err'] = 'Unknown';
    $hdrErr = $hdrPos =  [];
    foreach ($this->crosstabCountVanHdr1 as $nlpKey => $vanField) {
      //nlp_debug_msg('$vanField',strToHex($vanField));
      $found = FALSE;
      foreach ($fileHdr as $fileCol=>$fileColName) {
        $fileColName = preg_replace("/[^A-Za-z0-9 ]/", '', $fileColName);
        //nlp_debug_msg('$fileColName',strToHex($fileColName));
        //$str = preg_replace("/[^A-Za-z0-9 ]/", '', $str);
        if(trim($fileColName) == $vanField) {
          $hdrPos[$nlpKey] = $fileCol;
          $found = TRUE;
          break;
        }
      }
      if(!$found) {
        $hdrErr[] = 'The crosstab export header "'.$vanField.'" is missing.';
      }
    }
    $name = $this->crosstabCountVanHdr2['balRet'];
    $found = FALSE;
    foreach ($fileHdr2 as $fileCol=>$fileColName) {
      if($fileColName == $name) {
        $hdrPos['balRet'] = $fileCol;
        $found = TRUE;
        break;
      }
    }
    if(!$found) {
      $hdrErr[] = 'The crosstab export header "'.$name.'" is missing.';
    }
    $fieldPos['pos'] = $hdrPos;
    $fieldPos['err'] = $hdrErr;
    $fieldPos['ok'] = empty($hdrErr);
    return $fieldPos;
  }

  public function updateCrosstabCounts($counts): bool
  {
    try {
      $this->connection->merge(self::CROSSTAB_COUNTS_TBL)
        ->keys(array(
          'county' => $counts['county'],
          'party' => $counts['party']))
        ->fields(array(
          'registeredVoters' => $counts['regVoters'],
          'registeredVoted' => $counts['regVoted']))
        ->execute();
    } catch (Exception $e) {
      nlp_debug_msg('e',$e->getMessage());
      return FALSE;
    }
    return TRUE;
  }

  public function fetchCrosstabCounts(): array
  {
    $query = $this->connection->select(self::CROSSTAB_COUNTS_TBL, 'c');
    $query->fields('c');
    $result = $query->execute();

    $counts = array();
    do {
      $count = $result->fetchAssoc();
      if(empty($count)) {break;}
      $counts[$count['county']][$count['party']]['regVoted'] = $count['registeredVoted'];
      $counts[$count['county']][$count['party']]['regVoters'] = $count['registeredVoters'];
    } while (TRUE);
    return $counts;
  }

}
