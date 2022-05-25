<?php

namespace Drupal\nlpservices;

use Drupal;
use Drupal\Core\File\FileSystemInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\Core\Database\Connection;

use Exception;
//use Drupal\nlpservices\NlpVoters;
//use Drupal\nlpservices\NlpReports;
//use Drupal\nlpservices\NlpNls;

/**
 * @noinspection PhpUnused
 */
class NlpExportNlsStatus
{
const DD_NL_STATUS_FILE = 'nl_status_report';

  protected FileSystemInterface $fileSystem;
  protected Connection $connection;
  protected NlpVoters $voters;
  protected NlpReports $reports;
  protected NlpNls $nls;
  
  public function __construct( $fileSystem,$connection,$voters,$reports,$nls) {
    $this->fileSystem = $fileSystem;
    $this->connection = $connection;
    $this->voters = $voters;
    $this->reports = $reports;
    $this->nls = $nls;
  }

  public static function create(ContainerInterface $container): NlpExportNlsStatus
  {
    return new static(
      $container->get('file.system'),
      $container->get('database'),
      $container->get('nlpservices.voters'),
      $container->get('nlpservices.reports'),
      $container->get('nlpservices.nls'),
    );
  }
  
  /** * * * * * * * * * * * * * * * * * * * * * * * * * * * * * *
   * createStatus
   *
   * Fill the file with the NL status information for the selected county or
   * counties.
   *
   * @param $all  -  report all the participating counties.
   * @param $county
   * @param $listUri
   * @return void
   */
  function createStatus($all, $county, $listUri) {
    $hdr = array('mcid', 'county', 'hd', 'precinct', 'First Name', 'Last Name', 'email', 'phone',
      'Signed up', 'Login Date', 'Reported', 'Attempts', 'P2V', 'Voters',
      'Email (formatted)');
    $fh = fopen($listUri, "w");
    fputcsv($fh, $hdr);
    
    if ($all) {
      $counties = $this->voters->getParticipatingCounties();
    } else {
      $counties = array($county);
    }

    foreach ($counties as $county) {
      // Get the list of all the NLs in a county.
      $nl_list = $this->nls->getCountyNls($county);
      //nlp_debug_msg('$nl_list',$nl_list);
      if (!$nl_list) {
        return;
      }
      $counts = $this->reports->getCountyReportCounts($county);
      foreach ($nl_list as $mcid => $nl) {
        // Count the number of voters assigned to this NL.
        $voterCount = $this->voters->getVoterCountByNl($mcid);
        //  Now get the contact attempt count and the successful f2f count.
        if (!empty($counts[$mcid]['attempts'])) {
          $attempts = $counts[$mcid]['attempts'];
          $contacts = $counts[$mcid]['contacts'];
        } else {
          $attempts = $contacts = '';
        }
        // restore the apostrophes.
        $nickname =  html_entity_decode($nl['nickname']);
        $lastName =  html_entity_decode($nl['lastName']);
        $email = $nl['email'];
        if (empty($email)) {
          $formatted_email = '';
        } else {
          $formatted_email = $nickname . ' ' . $lastName . '<' . $email . '>';
        }
        $nl_row = array();
        $nl_row[] = $nl['mcid'];
        $nl_row[] = $nl['county'];
        $nl_row[] = $nl['hd'];
        $nl_row[] = $nl['precinct'];
        $nl_row[] = $nickname;
        $nl_row[] = $lastName;
        $nl_row[] = $email;
        $nl_row[] = $nl['phone'];
        $nl_row[] = $nl['signedUp'];
        $nl_row[] = $nl['loginDate']."\t"; //Stop Excel date conversion.
        $nl_row[] = $nl['resultsReported'];
        $nl_row[] = $attempts;
        $nl_row[] = $contacts;
        $nl_row[] = $voterCount;
        $nl_row[] = $formatted_email;
        fputcsv($fh, $nl_row);
      }
    }
    fclose($fh);
  }
  
  
  public function getNlsStatusFile($all=FALSE): string
  {
    
    $factory = Drupal::service('tempstore.private');
    $store = $factory->get('nlpservices.session_data');
    $county = $store->get('County');

    // Create temp files for the status list.
    // The file will be managed files to be deleted by Drupal after 6 hours.
    $temp_dir = 'public://temp';
    // Use a date in the name to make the file unique., just in case two people
    // are doing an export at the same time.
    $createDate = date('Y-m-d-H-i-s', time());
    // Create the status file.
    $listUri = $temp_dir . '/' . self::DD_NL_STATUS_FILE . '-' . strtolower($county) . '-' . $createDate . '.csv';
    //$file = file_save_data('', $listUri, FileSystemInterface::EXISTS_REPLACE);
    $file = Drupal::service('file.repository')->writeData('', $listUri, FileSystemInterface::EXISTS_REPLACE);
    $file->setTemporary();
    try {
      $file->save();
    }
    catch (Exception $e) {
      nlp_debug_msg('e',$e->getMessage());
      return '';
    }

    // Now fill them with information.
    $this->createStatus($all, $county, $listUri);
    // Provide the external link to the user so the file can be downloaded.
    //$list_url = file_create_url($listUri);
    $url = Drupal::service('file_url_generator')->generateAbsoluteString($listUri);
    $output = '<fieldset><legend>NL status report</legend>';
    $output .= "<p>This file contains a list of NLs for your county. It contains the current status of activity by the 
NL for this election cycle and voting results if available.</p>";
    
    $output .= '<a href="'.$url.'">Right-click to download canvassing and voting results for your NLs.  </a>';
    
    $output .= '</fieldset>';
    return $output;
  }

}
