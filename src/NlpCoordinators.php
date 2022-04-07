<?php

namespace Drupal\nlpservices;

use Drupal\Core\Database\Connection;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Exception;

class NlpCoordinators {
  
  const COORDINATOR_TBL = "nlp_coordinator";
  const PCT_COORDINATOR_TBL = "nlp_pct_coordinator";
  
  public array $coordinatorList = [
    'cIndex','county','mcid','firstName','lastName','email','phone','scope','hd','partial',
  ];
  
  protected Connection $connection;
  
  public function __construct($connection) {
    $this->connection = $connection;
  }
  
  public static function create(ContainerInterface $container): NlpCoordinators
  {
    return new static(
      $container->get('database'),
    );
  }
  
  public function createCoordinator($req) {
    $fields = array();
    foreach ($req as $key => $value) {
      if(in_array($key,$this->coordinatorList)) {
        $fields[$key] = $value;
      }
    }
    $precinctList = '';
    $fields['partial'] = 0;
    if($req['scope'] == 'precinct') {
      $precinctList = $req['partial'];
      $fields['partial'] = 1;
    }
    //nlp_debug_msg('$fields',$fields);
    try {
      $cIndex = $this->connection->insert(self::COORDINATOR_TBL)
        ->fields($fields)
        ->execute();
    } catch (Exception $e) {
      nlp_debug_msg('e',$e->getMessage());
      return;
    }
    
    // If we have a list of precincts for this coordinator, add the list to the database.
    if(!empty($precinctList)) {
      $precincts = explode(',', $precinctList);
      //nlp_debug_msg('$precincts',$precincts);
      foreach ($precincts as $precinct) {
        try {
          //db_insert(self::PCT_COORDINATOR_TBL)
          $this->connection->insert(self::PCT_COORDINATOR_TBL)
            ->fields(array(
              'cIndex' => $cIndex,
              'precinct' => trim($precinct),
            ))
            ->execute();
        } catch (Exception $e) {
          nlp_debug_msg('e',$e->getMessage());
          return;
        }
      }
    }
  }
  
  public function getCoordinators($county): array
  {
    $query = $this->connection->select(self::COORDINATOR_TBL, 'c');
    $query->fields('c');
    $query->condition('county',$county);
    $result = $query->execute();

    $coordinators = array();
    do {
      $coordinator = $result->fetchAssoc();
      //nlp_debug_msg('record', $record);
      if(empty($coordinator)) {break;}
      $precincts = array();
      $precinctList = '';
      if ($coordinator['partial']) {
        $query = $this->connection->select(self::PCT_COORDINATOR_TBL, 'p');
        $query->fields('p');
        $query->condition('CIndex',$coordinator['cIndex']);
        $pResult = $query->execute();

        do {
          $precinct = $pResult->fetchAssoc();
          if(empty($precinct)) {break;}
          $precincts[$precinct['precinct']] = $precinct['precinct'];
        } while (TRUE);
        $precinctList = implode(',', $precincts);
      }
      $coordinator['precincts'] = $precincts;
      $coordinator['precinctList'] = $precinctList;
      $coordinators[$coordinator['cIndex']] = $coordinator;
    } while (TRUE);
    return $coordinators;
  }
  
  public function deleteCoordinator($cIndex) {
    $this->connection->delete(self::COORDINATOR_TBL)
      ->condition('cIndex', $cIndex)
      ->execute();
    // Delete any precincts defined for this coordinator, if any.
    $this->connection->delete(self::PCT_COORDINATOR_TBL)
      ->condition('cIndex', $cIndex)
      ->execute();
  }

  function getCoordinator($region): array
  {
    //nlp_debug_msg('$region',$region);
    if(empty($region['coordinators']) OR empty($region['county'])) {
      return array();  // No coordinators assigned yet.
    }
    $allCos = $region['coordinators'];
    $county  = $region['county'];
    // If there is a coordinator assigned to the precinct, use that person.  Else
    // chose the house district coordinator.  If there is no HD coordinator,
    // then the county coordinator.  There should be at least one of these.
    // If not, no one will be chosen.
    $precinct = $region['pct'];
    $hd = $region['hd'];
    if(empty($allCos[$county])) {
      return array();  // No one in the county is a coordinator.
    }
    $countyCos = $allCos[$county];
    //nlp_debug_msg('$countyCos',$countyCos);
    $co = array();
    if(isset($countyCos['pct'][$precinct])) {
      $co = $countyCos['pct'][$precinct];
    } elseif(isset($countyCos['hd'][$hd])) {
      $co = $countyCos['hd'][$hd];
    } elseif (isset($countyCos['county'])) {
      $co = $countyCos['county'];
    }
    //nlp_debug_msg('$co',$co);
    return $co;
  }
  
  function getAllCoordinators(): array
  {
    $query = $this->connection->select(self::COORDINATOR_TBL, 'c');
    $query->fields('c');
    $result = $query->execute();

    $cos = array();
    do {
      $co = $result->fetchAssoc();
      if(empty($co)) {break;}
      $cos[$co['cIndex']] = $co;
    } while (TRUE);
    $coordinators = array();
    foreach ($cos as $co) {
      $county = $co['county'];
      $scope = $co['scope'];
      $cIndex = $co['cIndex'];
      switch ($scope) {
        case 'precinct':
          $query = $this->connection->select(self::PCT_COORDINATOR_TBL, 'p');
          $query->fields('p');
          $query->condition('CIndex',$cIndex);
          $tResult = $query->execute();

          do {
            $record = $tResult->fetchAssoc();
            if(empty($record)) {break;}
            $precinct = $record['precinct'];
            if(!isset($coordinators[$county]['pct'][$precinct])) {
              $coordinators[$county]['pct'][$precinct] = $co;
            }
          } while (TRUE);
          break;
        case 'hd':
          $hd = $co['hd'];
          if(!isset($coordinators[$county]['hd'][$hd])) {
            $coordinators[$county]['hd'][$hd] = $co;
          }
          break;
        case 'county':
          if(!isset($coordinators[$county]['county'])) {
            $coordinators[$county]['county'] = $co;
          }
          break;
      }
    }
    return $coordinators;
  }
  
}
