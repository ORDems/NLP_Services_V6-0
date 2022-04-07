<?php

namespace Drupal\nlpservices;

use Drupal\Core\Database\Connection;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Exception;

class NlpLegislativeFixes
{
  
  const LEG_FIX_TBL = 'nlp_legislative_fixes';

  private array $legList = ['county','mcid','firstName','lastName','hd','precinct'];
  
  protected Connection $connection;
  
  public function __construct($connection) {
    $this->connection = $connection;
  }
  
  public static function create(ContainerInterface $container): NlpLegislativeFixes
  {
    return new static(
      $container->get('database'),
    );
  }
  
  public function createLegFix($fix): bool
  {
    //nlp_debug_msg('$fix',$fix);
    $fields = array();
    foreach ($fix as $key => $value) {
      if (in_array($key,$this->legList)) {
        $fields[$key] = $value;
      } else {
        $fields[$key] = NULL;
      }
    }
    try {
      $this->connection->merge(self::LEG_FIX_TBL)
        ->keys(array('mcid' => $fix['mcid']))
        ->fields($fields)
        ->execute();
    } catch (Exception $e) {
      nlp_debug_msg('e', $e->getMessage());
      return FALSE;
    }
    return TRUE;
  }
  
  public function getLegFixes($county)
  {
    try {
      $query = $this->connection->select(self::LEG_FIX_TBL, 'l');
      $query->fields('l');
      $query->condition('county', $county);
      $query->orderBy('hd');
      $query->orderBy('precinct');
      $query->orderBy('lastName');
      $query->orderBy('firstName');
      $result = $query->execute();
    } catch (Exception $e) {
      nlp_debug_msg('e', $e->getMessage());
      return FALSE;
    }
    $fixes = array();
    do {
      $fix = $result->fetchAssoc();
      //nlp_debug_msg('fix', $fix);
      if (empty($fix)) {
        break;
      }
      $fixes[$fix['mcid']] = $fix;
    } while (TRUE);
    return $fixes;
  }
  
  public function deleteLegFix($county, $mcid): bool
  {
    try {
      $this->connection->delete(self::LEG_FIX_TBL)
        ->condition('county', $county)
        ->condition('mcid', $mcid)
        ->execute();
    } catch (Exception $e) {
      nlp_debug_msg('e', $e->getMessage());
      return FALSE;
    }
    return TRUE;
  }
}
