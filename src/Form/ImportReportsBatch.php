<?php

const MAX_QUEUE_LIMIT = '10';

/** * * * * * * * * * * * * * * * * * * * * * * * * * * * * * *
 * importReportsBatch
 *
 * Read the provided file and save the Dems.
 *
 * @param $arg
 * @param $context
 * @noinspection PhpUnused
 */
function importReportsBatch($arg,&$context) {
  
  $context['finished'] = TRUE;
  $uri = $arg['uri'];
  
  $fh = fopen($uri, "r");
  if (empty($fh)) {
    Drupal::logger('nlpservices')->notice('Failed to open reports file.');
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
    unset($fieldPos['EOR']);
    unset($fieldPos['reportIndex']);
    //nlp_debug_msg('$fieldPos',$fieldPos);
    $context['sandbox']['fieldPos'] = $fieldPos;
  } else {
    // Seek to where we will restart.
    $seek = $context['sandbox']['seek'];
    fseek($fh, $seek);
    $fieldPos = $context['sandbox']['fieldPos'];
  }
  $recordCount = $context['sandbox']['recordCount'];
  
  $reports = Drupal::getContainer()->get('nlpservices.reports');
  //$reports->initializeBatch();
  
  $done = TRUE;
  $loopCount = 0;
  do {
    $report = fgetcsv($fh);
    if(empty($report)) {break;}
    $loopCount++;
    $recordCount++;
    $fields = array();
    foreach ($fieldPos as $key => $column) {
      if($key == 'text') {
        //$fields[$key] = preg_replace('/\s+|[[:^print:]]/', '', $report[$column]);
        $fields[$key] = trim(strip_tags(htmlentities(stripslashes($report[$column]),ENT_QUOTES)));
      } else {
        $fields[$key] = $report[$column];
      }
      //$fields[$key] = preg_replace('/[0-9\@\.\;\" "]+/', '', $report[$column]);
    }
    //nlp_debug_msg('fields', $fields);
  
    $contactDate = $fields['contactDate'];
    $contactTime = strtotime($contactDate);
    $fields['contactDate'] = date('Y-m-d',$contactTime);
    
    $value = str_replace("'","\'",$fields['value']);
    $fields['value'] = $value;
    $batchLimit = $reports->insertNlReports($fields);
  
    if ($batchLimit) {
      $done = FALSE;
      $seek = ftell($fh);
      $context['sandbox']['seek'] = $seek;
      $percent = $seek/$filesize;
      $context['finished'] = $percent;
      // We are done when the read pointer is at the end.
      if($seek == $filesize) {
        $done = TRUE;
      }
      $context['sandbox']['recordCount'] = $recordCount;
      break;
    }
    
  } while (TRUE);  // Keep looping to read records until the break at EOF.
  
  // Finish the batch if we are done.
  if($done) {
    $reports->flushNlReports();
    $context['finished'] = 1;
    $context['results']['recordCount'] = $recordCount;
    $context['results']['uri'] = $uri;
  }
}

/** * * * * * * * * * * * * * * * * * * * * * * * * * * * * * *
 * importReportsBatchFinished
 *
 * The batch operation is finished.  Report the results.
 *
 * @param $success
 * @param $results
 * @param $operations
 * @noinspection PhpUnusedParameterInspection
 */
function importReportsBatchFinished($success, $results, $operations) {
  $messenger = Drupal::messenger();
  $messenger->addStatus( t('Batch finished.'));
  if ($success) {
    $recordCount = $results['recordCount'];
    $messenger->addStatus( t('@count records processed.',
      array('@count' => $recordCount)));
    $messenger->addStatus( t('The reports file successfully updated.'));
  }
  else {
    $messenger->addError( t('An error occurred.'));
  }
}
