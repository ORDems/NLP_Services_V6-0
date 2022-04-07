<?php

namespace Drupal\nlpservices;

use Drupal;
use Drupal\Core\File\FileSystemInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Exception;
//use Drupal\nlpservices\NlpVoters;
//use Drupal\nlpservices\NlpReports;
//use Drupal\nlpservices\NlpNls;
//use Drupal\nlpservices\NlpTurfs;
//use Drupal\nlpservices\NlpMatchbacks;

/**
 * @noinspection PhpUnused
 */
class NlpExportTurfStatus
{

  const DD_TURF_CANVASSING_STATUS_FILE = 'turf_canvassing_status';
  
  protected FileSystemInterface $fileSystem;
  protected NlpVoters $voters;
  protected NlpReports $reports;
  protected NlpNls $nls;
  protected NlpTurfs $turfs;
  protected NlpMatchbacks $matchbacks;
  
  
  public function __construct($fileSystem,$voters,$reports,$nls,$turfs,$matchbacks) {
    $this->fileSystem = $fileSystem;
    $this->voters = $voters;
    $this->reports = $reports;
    $this->nls = $nls;
    $this->turfs = $turfs;
    $this->matchbacks = $matchbacks;
  }
  
  public static function create(ContainerInterface $container): NlpExportTurfStatus
  {
    return new static(
      $container->get('file.system'),
      $container->get('nlpservices.voters'),
      $container->get('nlpservices.reports'),
      $container->get('nlpservices.nls'),
      $container->get('nlpservices.turfs'),
      $container->get('nlpservices.matchbacks'),
    );
  }

  /** * * * * * * * * * * * * * * * * * * * * * * * * * * * * * *
   * turfResults
   *
   * @param $tempFileName
   * @param $county
   * @return bool
   */
  function turfResults($tempFileName,$county): bool
  {
    $header = array('hd','precinct','First Name','Last Name','Voters','voted','Percent',
      'Pledge to Vote','voted','Percent','Attempts','Turf Name');
    $fh = fopen($tempFileName,"w");
    fputcsv($fh, $header);

    $turfRecords = $this->turfs->getCountyTurfs($county);
    //nlp_debug_msg('$turfRecords',$turfRecords);
    //nlp_debug_msg('$county',$county);
    if (empty($turfRecords)) {
      //nlp_set_msg('No turfs','error');
      return FALSE;
    }
    //  This function can take a lot of time with a large county.
    //  Keep track of the elapsed time in case we need to upgrade the server.
    set_time_limit(60);
    
    foreach ($turfRecords as $turfRecord) {
      $turfIndex = $turfRecord['turfIndex'];
      $voters = $this->voters->getVotersInTurf($turfIndex);
      
      if (empty($voters)) {continue;}
      // For each voter determine if a vote was recorded and if there was a
      // face-to-face contact.
      $voterCount = $votedCount = $surveyResponsesCnt = $surveyResponsesVoted = $attempts = 0;
      foreach ($voters as $vanid) {
        // Get the status of ballot returned (indicates voted).
        $voted = $this->matchbacks->MatchbackExists($vanid);
        // Add to the count of voters and the count of those who voted.
        $voterCount++;
        if (!empty($voted)) {$votedCount++;}
        // Now check if this voter was contacted face-to-face.
        $canvassResponse = $this->reports->contactAttempt($vanid);
        $surveyResponse = $canvassResponse['survey'];
        if ($surveyResponse) {
          $surveyResponsesCnt++;
          if (!empty($voted)) {$surveyResponsesVoted++;}
        }
        $attempt = $canvassResponse['attempt'];
        
        if ($attempt) {
          $attempts++;
        }
      }
      // Create the display of counts for this turf.
      $nickname =  html_entity_decode($turfRecord['nlFirstName']);
      $lastName =  html_entity_decode($turfRecord['nlLastName']);
      $percentVoted = ($voterCount > 0)?round($votedCount/$voterCount*100,1).'%':'0%';
      $surveyResponses_pc = ($surveyResponsesCnt > 0)?round($surveyResponsesVoted/$surveyResponsesCnt*100,1).'%':'0%';
      
      $turfOutput = [];
      $turfOutput[] = $turfRecord['turfHd'];
      $turfOutput[] = $turfRecord['turfPrecinct'];
      $turfOutput[] = $nickname;
      $turfOutput[] = $lastName;
      $turfOutput[] = $voterCount;
      $turfOutput[] = $votedCount;
      $turfOutput[] = $percentVoted;
      $turfOutput[] = $surveyResponsesCnt;
      $turfOutput[] = $surveyResponsesVoted;
      $turfOutput[] = $surveyResponses_pc;
      $turfOutput[] = $attempts;
      $turfOutput[] = $turfRecord['turfName'];
      fputcsv($fh, $turfOutput);
    }
    fclose($fh);
    
    return TRUE;
  }
  
  
  function getTurfStatus(): string
  {
    //nlp_debug_msg('getTurfStatus');

    $factory = Drupal::service('tempstore.private');
    $store = $factory->get('nlpservices.session_data');
    $county = $store->get('County');
    
    $tempDir = 'public://temp';
    // Use a date in the name to make the file unique., just in case two people
    // are doing an export at the same time.
    $contactDate = date('Y-m-d-H-i-s',time());
    // Open a temp file for receiving the records.
    $baseUri = $tempDir.'/'.self::DD_TURF_CANVASSING_STATUS_FILE.'-'.strtolower($county).'-'.$contactDate;
    $tempUri = $baseUri.'.csv';
    // Create a managed file for temporary use.  Drupal will delete after 6 hours.
    $file = file_save_data('', $tempUri, FileSystemInterface::EXISTS_REPLACE);
    $file->setTemporary();
    try{
      $file->save();
    }
    catch (Exception $e) {
      nlp_debug_msg('e',$e->getMessage());
      return '';
    }
    // Create the list of turfs with status.
    $results = $this->turfResults($tempUri,$county);
    //nlp_debug_msg('$results',$results);
    // Create the display with the link to the file.
    $output = '';
    if($results) {
      $url = file_create_url($tempUri);
      $output .= "<h2>".$county." County</h2><h2>A list of turfs with NL activity and voting results.</h2>";
      $output .= '<a href="'.$url.'">Right-click to download canvassing and voting results for each turf.  </a>';
    } else {
      $output .= "<h2>".$county." County</h2><p>There are no turfs with results.</p>";
    }
    return $output;
  }
  
}