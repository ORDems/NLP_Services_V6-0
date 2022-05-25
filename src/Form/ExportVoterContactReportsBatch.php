<?php

/** * * * * * * * * * * * * * * * * * * * * * * * * * * * * * *
 * export_voter_contact_reports
 *
 * Query the database and write records to a file.
 *
 * @param $arg
 * @param $context
 * @return void
 * @noinspection PhpUnused
 */
function exportVoterContactReportsBatch($arg,&$context)
{
  $messenger = Drupal::messenger();
  // Retrieve the values determined when we validated the form submittal
  $reportsUri = $arg['uri'];
  $rowCount = $arg['rowCount'];

  // Open the input file and position for writing at the end.
  $fh = fopen($reportsUri, "a");
  if (empty($fh)) {
    $messenger->addStatus('File open error: @'.$reportsUri);
    $context['finished'] = TRUE;
    return;
  }
  $context['finished'] = 0;
  if(empty($context['sandbox']['next_record'])) {
    $nextRecord = 0;
  } else {
    // Seek to where we will restart.
    $nextRecord = $context['sandbox']['next_record'];
  }
  $nlsObj = Drupal::getContainer()->get('nlpservices.nls');
  $reportsObj = Drupal::getContainer()->get('nlpservices.reports');

  $result = $reportsObj->selectAllReports($nextRecord);
  // Get the records one at a time.
  $recordCount = 0;
  do {
    $rawRecord = $result->fetchAssoc();
    if (!$rawRecord) {break;}  // Last record processed.
    $recordCount++;
    $record = $reportsObj->prepareExportRecord($rawRecord);
    //nlp_debug_msg('$record',$record);
    // Get the name of the NL who recorded this report, if we have it.
    $nl = $nlsObj->getNlById($record['mcid']);
    $record['nickname'] = $record['lastName'] = '';
    if(!empty($nl['lastName'])) {
      $record['nickname'] = html_entity_decode($nl['nickname']);
      $record['lastName'] = html_entity_decode($nl['lastName']);
    }
    $record['EOR'] = "EOR";
    fputcsv($fh, $record);

  } while (TRUE);
  fclose($fh);
  // Finish the batch if we are done.
  if($recordCount != $reportsObj::BATCH_LIMIT) {
    // We're done with the last record.
    $context['finished'] = 1;
    $context['results']['records'] = $nextRecord+$recordCount;
    $context['results']['uri'] = $reportsUri;
  } else {
    // Not done.
    $nextRecord += $reportsObj::BATCH_LIMIT;
    $context['sandbox']['next_record'] = $nextRecord;
    $percent = $nextRecord/$rowCount;
    if($percent == 1) {
      $percent = .999;
    }
    $context['finished'] = $percent;
  }
}

/** * * * * * * * * * * * * * * * * * * * * * * * * * * * * * *
 * exportVoterContactReportsBatchFinished
 *
 * A nice report when we are done.
 *
 * @param  $success
 * @param  $results
 * @param  $unused
 * @noinspection PhpUnusedParameterInspection
 */
function exportVoterContactReportsBatchFinished($success, $results, $unused)
{
  $messenger = Drupal::messenger();

  if ($success) {
    $records = $results['records'];
    $messenger->addStatus($records.' reports exported. ');
    $messenger->addStatus('NL reports successfully exported.');
  }
  else {
    $messenger->addStatus('An error occurred.');
  }
}
