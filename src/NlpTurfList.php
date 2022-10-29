<?php

namespace Drupal\nlpservices;

use Drupal;
use Drupal\Core\File\FileSystemInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Exception;

/**
 * @noinspection PhpUnused
 */

class NlpTurfList
{
  
  const NL_TURF_FILE = 'nlp_turfs';
  
  protected FileSystemInterface $fileSystem;
  protected NlpTurfs $nlpTurfs;
  protected NlpVoters $nlpVoters;
  protected ApiVoter $apiVoter;
  
  public function __construct($fileSystem, $nlpTurfs, $nlpVoters, $apiVoter)
  {
    $this->fileSystem = $fileSystem;
    $this->nlpTurfs = $nlpTurfs;
    $this->nlpVoters = $nlpVoters;
    $this->apiVoter = $apiVoter;
  }
  
  public static function create(ContainerInterface $container): NlpTurfList
  {
    return new static(
      $container->get('file.system'),
      $container->get('nlpservices.turfs'),
      $container->get('nlpservices.voters'),
      $container->get('nlpservices.api_voter'),
    );
  }
  
  /** * * * * * * * * * * * * * * * * * * * * * * * * * * * * * *
   * nlTurfs
   *
   * @param $tempFileName
   * @return bool
   */
  function nlTurfs($tempFileName): bool
  {
    $nlpEncrypt = Drupal::getContainer()->get('nlpservices.encryption');
  
    $config = Drupal::service('config.factory')->get('nlpservices.configuration');
    $database = 0;  // VoterFile.
    $apiKeys = $config->get('nlpservices-api-keys');
    $committeeKey = $apiKeys['State Committee'];
    $committeeKey['API Key'] = $nlpEncrypt->encrypt_decrypt('decrypt', $committeeKey['API Key']);
    
    
    //$turfIndexes = [];
    $header = $this->nlpTurfs->getColumnNames();
    $fh = fopen($tempFileName, "w");
    fputcsv($fh, $header);
    
    $result = $this->nlpTurfs->selectAllTurfs();
    if (empty($result)) {
      return FALSE;
    }
    
    do {
      $turf = $this->nlpTurfs->getNextTurf($result);
      if (empty($turf)) {
        break;
      }
      if(!empty($turf['turfCd'])) {
        $cd = $turf['turfCd'];
      } else {
        $turfVanids = $this->nlpVoters->getVotersInTurf($turf['turfIndex']);
        $vanid = reset($turfVanids);
        $cd = $this->apiVoter->getVoterCd($committeeKey,$database,$vanid);
        $this->nlpTurfs->setTurfCd($turf['turfIndex'],$cd);
        $turf['turfCd'] = $cd;
      }
      //$turfIndexes[$turf['turfIndex']] = $cd;
      fputcsv($fh, $turf);
    } while (TRUE);
    fclose($fh);
    //nlp_debug_msg('$turfIndexes',$turfIndexes);
    return TRUE;
  }
  
  
  function getTurfList(): string
  {
    $tempDir = 'public://temp';
    // Use a date in the name to make the file unique., just in case two people
    // are doing an export at the same time.
    $contactDate = date('Y-m-d-H-i-s', time());
    // Open a temp file for receiving the records.
    $baseUri = $tempDir . '/' . self::NL_TURF_FILE . '-' . $contactDate;
    $tempUri = $baseUri . '.csv';
    // Create a managed file for temporary use.  Drupal will delete after 6 hours.
    
    $file = Drupal::service('file.repository')->writeData('', $tempUri, FileSystemInterface::EXISTS_REPLACE);
    //$file = file_save_data('', $tempUri, FileSystemInterface::EXISTS_REPLACE);
    $file->setTemporary();
    try {
      $file->save();
    } catch (Exception $e) {
      nlp_debug_msg('e', $e->getMessage());
      return '';
    }
    // Create the list of turfs with status.
    $results = $this->nlTurfs($tempUri);
    //nlp_debug_msg('$results',$results);
    // Create the display with the link to the file.
    $output = '';
    if ($results) {
      //$url = file_create_url($tempUri);
      $url = Drupal::service('file_url_generator')->generateAbsoluteString($tempUri);
      $output .= "<h2>A list of turfs.</h2>";
      $output .= '<a href="' . $url . '">Right-click to download canvassing and voting results for each turf.  </a>';
    } else {
      $output .= "</h2><p>There are no turfs.</p>";
    }
    return $output;
  }
  
  
}