<?php

namespace Drupal\nlpservices;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Database\Connection;
use Drupal\Core\File\FileSystemInterface;
use Exception;
use Symfony\Component\DependencyInjection\ContainerInterface;
//use Drupal\nlpservices\NlpPaths;
//use Drupal\nlpservices\NlpNls;


class NlpTurfs {
  
  const TURF_TBL = 'nlp_turf';

  protected ConfigFactoryInterface $config;
  protected Connection $connection;
  protected FileSystemInterface $fileSystem;
  protected NlpNls $nls;
  protected NlpPaths $paths;

  public function __construct($config, $connection, $fileSystem, $nls, $paths) {
    $this->config = $config;
    $this->connection = $connection;
    $this->fileSystem = $fileSystem;
    $this->nls = $nls;
    $this->paths = $paths;
  }
  
  public static function create(ContainerInterface $container): NlpTurfs
  {
    return new static(
      $container->get('config.factory'),
      $container->get('database'),
      $container->get('file.system'),
      $container->get('nlpservices.nls'),
      $container->get('nlpservices.paths'),
    );
  }
  
  public function setAllTurfsDelivered($mcid,$county) {
    try {
      $query = $this->connection->select(self::TURF_TBL, 'i');
      $query->fields('i');
      $query->condition('county',$county);
      $query->condition('mcid',$mcid);
      $result = $query->execute();
    } catch (Exception $e) {
      nlp_debug_msg('e', $e->getMessage() );
      return;
    }
    do {
      $turf = $result->fetchAssoc();
      if(empty($turf)) {break;}
      $this->setTurfDelivered($turf['turfIndex']);
    } while (TRUE);
  }
  
  public function setTurfDelivered($turfIndex) {
    $isoDate = explode('T', date('c'));  // date/time in ISO format.
    try {
      $this->connection->merge(self::TURF_TBL)
        ->keys(array('turfIndex' => $turfIndex,))
        ->fields(array('delivered' => $isoDate[0],))
        ->execute();
    } catch (Exception $e) {
      nlp_debug_msg('e', $e->getMessage() );
      return;
    }
  }
  
  public function getCountyTurfs($county): array
  {
    try {
      $query = $this->connection->select(self::TURF_TBL, 'i');
      $query->fields('i');
      $query->condition('county',$county);
      $result = $query->execute();
    }
    catch (Exception $e) {
      nlp_debug_msg('e', $e->getMessage() );
      return [];
    }
    $turfArray = array();
    do {
      $turfRecord = $result->fetchAssoc();
      if (empty($turfRecord)) {break;}
      $turfIndex = $turfRecord['turfIndex'];
      $turfArray[$turfIndex] = $turfRecord;
    } while (TRUE);
    return $turfArray;
  }
  
  public function getCountyTurfCount($county): int
  {
    try {
      $query = $this->connection->select(self::TURF_TBL, 'i');
      $query->condition('county',$county);
      $query->addField('i','turfIndex');
      $turfCount = $query->countQuery()->execute()->fetchField();    }
    catch (Exception $e) {
      nlp_debug_msg('e', $e->getMessage() );
      return 0;
    }
    return $turfCount;
  }

  public function getTurf($turfIndex): array
  {
    try {
      $query = $this->connection->select(self::TURF_TBL, 'i');
      $query->fields('i');
      $query->condition('turfIndex',$turfIndex);
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
  
  public function createTurf($turf) {
    $turf['commitDate'] = date('Y-m-d',time());
    try {
      $turfIndex = $this->connection->insert(self::TURF_TBL)
        ->fields($turf)
        ->execute();
    }
    catch (Exception $e) {
      nlp_debug_msg('e', $e->getMessage()  );
      return FALSE;
    }
    return $turfIndex;
  }

  public function getTurfHD($county) {
    try {
      $query = $this->connection->select(self::TURF_TBL, 't');
      $query->addField('t', 'turfHd');
      $query->condition('t.county',$county);
      $result = $query->execute();
    }
    catch (Exception $e) {
      nlp_debug_msg('e', $e->getMessage() );
      return FALSE;
    }
    $uhd = array();
    do {
      $hdRec = $result->fetchAssoc();
      if(empty($hdRec)) {break;}
      $hd = $hdRec['turfHd'];
      $uhd[$hd] = $hd;
    } while (TRUE);
    if(empty($uhd)) {return NULL;}
    ksort($uhd);
    return array_values($uhd);
  }
  
  function getTurfPct($county,$hd) {
    // Get the list of precinct numbers with at least one NL with a turf in
    // this HD, order numerically by precinct number.
    try {
      $query = $this->connection->select(self::TURF_TBL, 't');
      $query->addField('t', 'turfPrecinct	');
      $query->condition('t.county',$county);
      $query->condition('t.turfHd',$hd);
      $result = $query->execute();
    }
    catch (Exception $e) {
      nlp_debug_msg('e', $e->getMessage() );
      return FALSE;
    }
    
    // Return if there are no precincts with a turf.
    $uPct = array();
    do {
      $pctRec = $result->fetchAssoc();
      if(empty($pctRec)) {break;}
      $pct = $pctRec['turfPrecinct'];
      $uPct[$pct] = $pct;
    } while (TRUE);
    if(empty($uPct)) {return NULL;}
    ksort($uPct);
    return array_values($uPct);
  }
  
  public function getTurfs($turfReq) {
    $county = $turfReq['county'];
    try {
      $query = $this->connection->select(self::TURF_TBL,'t');
      $query->fields('t');
      $query->condition('t.county',$county);
      if(isset($turfReq['hd'])) {
        $query->condition('t.turfHd',$turfReq['hd']);
      }
      if(isset($turfReq['pct'])) {
        $query->condition('t.turfPrecinct',$turfReq['pct']);
      }
      $result = $query->execute();
    }
    catch (Exception $e) {
      nlp_debug_msg('e', $e->getMessage() );
      return FALSE;
    }

    $turfArray = [];
    do {
      $turf = $result->fetchAssoc();
      //nlp_debug_msg('$turf',$turf);
      if (empty($turf)) {break;}
      $turfIndex = $turf['turfIndex'];
      $turf['hd'] = 0;
      $turf['precinct'] = '';
      $turf['nickname'] = '';
      $turf['lastName'] = '';
      $nl = $this->nls->getNlById($turf['mcid']);
      if(!empty($nl)) {
        $turf['hd'] = $nl['hd'];
        $turf['precinct'] = $nl['precinct'];
        $turf['nickname'] = $nl['nickname'];
        $turf['lastName'] = $nl['lastName'];
      }
      $turfArray[$turfIndex] = $turf;
    } while (TRUE);
    return $turfArray;
  }
  
  public function createTurfDisplay($turfArray): array
  {
    $turfDisplay = array();
    foreach ($turfArray as $turfIndex=> $turf) {
      $turfDisplay[$turfIndex] = $turf['turfIndex'].' '.$turf['commitDate'].' '
        .$turf['nlFirstName'].' '.$turf['nlLastName'].': '
        .$turf['turfName'].', pct-'.$turf['precinct'];
      if(empty($turf['turfPdf'])) {
        $turfDisplay[$turfIndex] .= '&nbsp; ***';
      }
    }
    return $turfDisplay;
  }
  
  private function unlinkFile($fileName,$path) {
    if($fileName != '') {
      $fullName = $path . $fileName;
      if(file_exists($fullName)) {
        $this->fileSystem->unlink($fullName);}
    }
  }
  
  public function removeTurf($turf): bool
  {
    $turfIndex = $turf['turfIndex'];
    $county = $turf['county'];
    // Get the filenames the PDF walksheet, mail list, and call list.
    try {
      $query = $this->connection->select(self::TURF_TBL, 'i');
      $query->fields('i');
      $query->condition('turfIndex',$turfIndex);
      $result = $query->execute();
    }
    catch (Exception $e) {
      nlp_debug_msg('e', $e->getMessage() );
      return FALSE;
    }
    $turfRecord = $result->fetchAssoc();
    // Delete the PDF file.
    if(!empty($turfRecord['turfPDF'])) {
      $fileName = $turfRecord['turfPDF'];
      $path = $this->paths->getPath('PDF',$county);
      $this->unlinkFile($fileName,$path);
    }

    // Delete the turf info in the turf table.
    try {
      $this->connection->delete(self::TURF_TBL)
        ->condition('turfIndex', $turfIndex)
        ->execute();
    }
    catch (Exception $e) {
      nlp_debug_msg('e', $e->getMessage() );
      return FALSE;
    }
    return TRUE;
  }
  
  public function updateTurfFiles($type,$fileName,$turfIndex): bool
  {
    switch ($type) {
      case 'mail':
        $file = 'turfMail';
        break;
      case 'call':
        $file = 'turfCall';
        break;
      case 'pdf':
        $file = 'turfPDF';
        break;
      default:
        return FALSE;
    }
    $this->connection->update(self::TURF_TBL)
      ->fields(array(
        $file => $fileName,
      ))
      ->condition('turfIndex',$turfIndex)
      ->execute();
    return TRUE;
  }

  public function turfExists($mcid,$county): array
  {
    $query = $this->connection->select(self::TURF_TBL, 'i');
    $query->fields('i');
    $query->condition('county',$county);
    $query->condition('mcid',$mcid);
    $result = $query->execute();
    $turfs = [];
    do {
      $dbTurf = $result->fetchAssoc();
      if (empty($dbTurf)) {break;}
      $turfIndex = $dbTurf['turfIndex'];
      $turfs[$turfIndex] = $dbTurf;
    } while (TRUE);
    if(empty($turfs)) {return [];}
    $turfArray['turfs'] = $turfs;
    $turfArray['turfCnt'] = count($turfs);
    $turfArray['turfIndex'] = key($turfs);
    return $turfArray;
  }

  public function createTurfNames($turfArray): array
  {
    $turfDisplay = array();
    foreach ($turfArray['turfs'] as $turfIndex=> $turf) {
      $turfDisplay[$turfIndex] = $turf['turfName'];
    }
    return $turfDisplay;
  }

  public function getNlsWithTurfs($county) {
    try {
      $query = $this->connection->select(self::TURF_TBL, 't');
      $query->fields('t');
      $query->condition('t.county',$county);
      $result = $query->execute();
    }
    catch (Exception $e) {
      nlp_debug_msg('getNlsWithTurfs', $e->getMessage() );
      return FALSE;
    }
    $nlsWithTurfs = [];
    do {
      $turfRec = $result->fetchAssoc();
      if(empty($turfRec)) {break;}
      $nlsWithTurfs[$turfRec['mcid']][$turfRec['turfIndex']] = $turfRec;
    } while (TRUE);
    if(empty($nlsWithTurfs)) {return NULL;}
    return $nlsWithTurfs;
  }
  
}
