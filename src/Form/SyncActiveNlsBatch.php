<?php

const MAX_QUEUE_LIMIT = '100';

/** * * * * * * * * * * * * * * * * * * * * * * * * * * * * * *
 * syncActiveNlsBatch
 *
 * Read the provided file and save the Dems.
 *
 * @param $arg
 * @param $context
 * @return void
 * @noinspection PhpUnused
 */
function syncActiveNlsBatch($arg,&$context) {
  
  $messenger = Drupal::messenger();
  $uri = $arg['uri'];
  $county = $arg['county'];
  $state = $arg['state'];
  
  $fixes = $arg['legislativeFixes'];
  $committeeKey = $arg['committeeKey'];
  $fh = fopen($uri, "r");
  if (empty($fh)) {
    Drupal::logger('nlpservices')->notice('Failed to open active NLs file.');
    $context['finished'] = TRUE;
    return;
  }
  
  $filesize = filesize($uri);
  //nlp_debug_msg('$filesize',$filesize);
  $context['finished'] = 0;
  // Position file at the start or where we left off for the previous batch.
  if(empty($context['sandbox']['seek'])) {
    // Read the header record.
    $context['sandbox']['recordCount'] = 0;
    $hdr = fgetcsv($fh);
    //nlp_debug_msg('$hdr',$hdr);
    $fieldPos = array_flip($hdr);
    //nlp_debug_msg('$fieldPos',$fieldPos);
    $context['sandbox']['fieldPos'] = $fieldPos;
  } else {
    // Seek to where we will restart.
    $seek = $context['sandbox']['seek'];
    fseek($fh, $seek);
    $fieldPos = $context['sandbox']['fieldPos'];
  }
  $recordCount = $context['sandbox']['recordCount'];
  $nls = Drupal::getContainer()->get('nlpservices.nls');
  $apiNls = Drupal::getContainer()->get('nlpservices.api_nls');
  
  $queCnt = 0;
  do {
    // Get the record with the VANID.
    $nl_raw = fgetcsv($fh);
    //nlp_debug_msg('nl', $nl_raw);
    
    if(empty($nl_raw)) {break;}
    $mcid = $nl_raw[$fieldPos['VanID']];
  
    $queCnt++;
    $recordCount++;
    
    // Get the contact info for a new NL.
    $nlRecord = $apiNls->getApiNls($committeeKey,$mcid);
    //nlp_debug_msg('$nlRecord',$nlRecord);
    if(empty($nlRecord)) {continue;}
    
    $nlHd = ltrim($nlRecord['hd'], "0");
    $pct = trim($nlRecord['precinct']);
    // Check if the HD or Pct is missing.
    if (empty($pct) OR empty($nlHd)) {
      // Check if we have a repair record.
      if (isset ($fixes[$mcid]))  {
        // Use the fixes for HD and Pct.
        $nlHd = $fixes[$mcid]['hd'];
        $pct = trim($fixes[$mcid]['pct']);
        $messenger->addWarning( "HD and Pct repaired for ".$nlRecord['nickname']." ". $nlRecord['lastName']);
      } else {
        $messenger->addWarning( "HD or Pct is missing for ".$nlRecord['nickname']." ". $nlRecord['lastName']);
        $nlHd = $pct = 0;
      }
    } else {
     
      if($state == 'Oregon') {
        $pctParts = explode('-',$pct);
        if(isset($pctParts[1])) {
          $pct = $pctParts[1];  // Discard the county name.
        }
      }
    }
    
    if(empty($nlRecord['nickname'])) {
      $nlRecord['nickname'] = $nlRecord['firstName'];
    }
    
    $nlRecord['hd'] = $nlHd;
    $nlRecord['precinct'] = $pct;
    $nlRecord['address'] = $nlRecord['address'].', '.$nlRecord['city'];
    unset($nlRecord['city']);
    
    $insertOk = $nls->createNl($nlRecord);
    //nlp_debug_msg('$insertOk',$insertOk);
    // INSERT this NL into the list for this group.
    if($insertOk) {
      $nls->createNlGrp($mcid,$county);
      // Create a status record if on does not already exist.
      $nl_status = $nls->getNlsStatus($mcid,$county);
      if (!empty($nl_status)) {
        $nls->setNlsStatus($nl_status);
      }
    }
    if($queCnt == MAX_QUEUE_LIMIT) {break;}
  } while (TRUE);
  
  $seek = ftell($fh);
  $context['sandbox']['seek'] = $seek;
  $context['finished'] = $seek/$filesize;
  $context['sandbox']['recordCount'] = $recordCount;
  $context['results']['recordCount'] = $recordCount;
  
  if($queCnt != MAX_QUEUE_LIMIT) {
    $context['finished'] = 1;
  }
  fclose($fh);
}

/** * * * * * * * * * * * * * * * * * * * * * * * * * * * * * *
 * syncActiveNlsBatchFinished
 *
 * The batch operation is finished.  Report the results.
 *
 * @param $success
 * @param $results
 * @param $operations
 * @noinspection PhpUnused
 * @noinspection PhpUnusedParameterInspection
 */
function syncActiveNlsBatchFinished($success, $results, $operations) {
  $messenger = Drupal::messenger();
  //$messenger->addStatus( t('Batch finished.'));
  if ($success) {
    $recordCount = $results['recordCount'];
    $messenger->addStatus( t('The list of active NLs is successfully updated.'));
    $messenger->addStatus( t('@count records processed.',array('@count' => $recordCount)));
  }
  else {
    $messenger->addError( t('An error occurred.'));
  }
}
