<?php

namespace Drupal\nlpservices;

use Drupal;
use Drupal\Core\File\FileSystemInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
//use Drupal\nlpservices\NlpAwards;
use Exception;


/**
 * @noinspection PhpUnused
 */
class NlpExportAwardStatus
{

  const NL_AWARD_FILE = 'nlp_awards';

  protected FileSystemInterface $fileSystem;
  protected NlpAwards $nlpAwards;

  public function __construct($fileSystem,$nlpAwards) {
    $this->fileSystem = $fileSystem;
    $this->nlpAwards = $nlpAwards;
  }

  public static function create(ContainerInterface $container): NlpExportAwardStatus
  {
    return new static(
      $container->get('file.system'),
      $container->get('nlpservices.awards'),
    );
  }

  /** * * * * * * * * * * * * * * * * * * * * * * * * * * * * * *
   * nlAwards
   *
   * @param $tempFileName
   * @return bool
   */
  function nlAwards($tempFileName): bool
  {
    $header = $this->nlpAwards->getColumnNames();
    $fh = fopen($tempFileName,"w");
    fputcsv($fh, $header);
    $mcids = $this->nlpAwards->getNlList();
    if (empty($mcids)) {return FALSE;}
    foreach ($mcids as $mcid) {
      $award = $this->nlpAwards->fetchAwardRecord($mcid);
      //nlp_debug_msg('$award',$award);
      //return TRUE;
      if (empty($award)) {break;}
      fputcsv($fh, $award);
    }
    fclose($fh);
    return TRUE;
  }


  function getAwardStatus(): string
  {
    $tempDir = 'public://temp';
    // Use a date in the name to make the file unique., just in case two people
    // are doing an export at the same time.
    $contactDate = date('Y-m-d-H-i-s',time());
    // Open a temp file for receiving the records.
    $baseUri = $tempDir.'/'.self::NL_AWARD_FILE.'-'.$contactDate;
    $tempUri = $baseUri.'.csv';
    // Create a managed file for temporary use.  Drupal will delete after 6 hours.

    $file = Drupal::service('file.repository')->writeData('', $tempUri, FileSystemInterface::EXISTS_REPLACE);
    //$file = file_save_data('', $tempUri, FileSystemInterface::EXISTS_REPLACE);
    $file->setTemporary();
    try{
      $file->save();
    }
    catch (Exception $e) {
      nlp_debug_msg('e',$e->getMessage());
      return '';
    }
    // Create the list of turfs with status.
    $results = $this->nlAwards($tempUri);
    //nlp_debug_msg('$results',$results);
    // Create the display with the link to the file.
    $output = '';
    if($results) {
      //$url = file_create_url($tempUri);
      $url = Drupal::service('file_url_generator')->generateAbsoluteString($tempUri);
      $output .= "<h2>A list of turfs with NL activity and voting results.</h2>";
      $output .= '<a href="'.$url.'">Right-click to download canvassing and voting results for each turf.  </a>';
    } else {
      $output .= "</h2><p>There are no awards recorded.</p>";
    }
    return $output;
  }
  
  

}