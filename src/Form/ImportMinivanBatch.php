<?php


/** * * * * * * * * * * * * * * * * * * * * * * * * * * * * * *
 * recordMinivanReports
 *
 * @param $reports
 * @param $fileType
 * @return array
 * @noinspection DuplicatedCode
 */
function recordMinivanReports($reports,$fileType): array
{
  //$nlpReportsObj = new NlpReports();
  $nlpReportsObj = Drupal::getContainer()->get('nlpservices.reports');

  //$nlObj = new NlpNls();
  $nlsObj = Drupal::getContainer()->get('nlpservices.nls');

  //$voterObj = new NlpVoters();
  $votersObj = Drupal::getContainer()->get('nlpservices.voters');


  //$activistCodesObj = new NlpActivistCodes();
  //$nlpHostile = $activistCodesObj->getActivistCode('NLPHostile');
  $config = Drupal::getContainer()->get('config.factory');
  $nlpConfig = $config->get('nlpservices.configuration');
  $electionDates = $nlpConfig->get('nlpservices-election-configuration');
  $cycle = $electionDates['nlp_election_cycle'];
  $nlpHostile = $nlpConfig->get('nlpservices_hostile_ac');
  $allowedResponseCodes = $nlpConfig->get('nlpservices_canvass_response_codes');
/*
  $contactTypeNames = [];
  foreach ($allowedResponseCodes as $allowedName=>$allowedCode) {
    $contactTypeNames[$allowedCode['code']] = $allowedName;
  }
*/
  //$contactTypeNames = array_keys($allowedResponseCodes);
  $surveyQuestions = $nlpConfig->get('nlpservices_survey_questions');
  //nlp_debug_msg('$surveyQuestions',$surveyQuestions);


  //$contextObj = new ApiSurveyContext();
  //$apiSurveyQuestionObj = new ApiSurveyQuestions($contextObj);
  $apiSurveyQuestionObj = Drupal::getContainer()->get('nlpservices.survey_question');

  $cid = $apiSurveyQuestionObj::CONTACT_TYPE_WALK;

  //$canvassResponsesObj = new NlpResponseCodes();
  //$apiResponsesObj = new ApiResponseCodes();
  //$expectedResultCodes = $apiResponsesObj->getApiExpectedResultCodes();
  //$allowedResponseCodes = $canvassResponsesObj->getAllowedResultCodes($expectedResultCodes);
  //$contactTypeNames = $canvassResponsesObj->getContactTypeNames();

  //$surveyResponseObj = new NlpSurveyResponse();
  //$surveyQuestionObj = new NlpSurveyQuestion($surveyResponseObj);
  //$surveyQuestionObj = Drupal::getContainer()->get('nlpservices.survey_question_nlp');
  //$questions = $surveyQuestionObj->getAllSurveyQuestions();



  //$minivanObj = new NlpMinivan($nlpReportsObj, $voterObj, $nlsObj);
  $minivanObj = Drupal::getContainer()->get('nlpservices.minivan');

  $defaultResult['county'] = NULL;
  $defaultResult['firstName'] = NULL;
  $defaultResult['lastName'] = NULL;
  $defaultResult['cycle'] = $cycle;
  //$nlpVotersObj = new NlpVoters();

  $counts = array('recordCnt' => 0,'processedCnt' => 0, 'duplicateCnt' => 0, 'rejectedCnt' => 0);

  //$transaction = db_transaction();
  $mcid = NULL;
  foreach ($reports as $report) {
    $counts['recordCnt']++;
    nlp_debug_msg('report', $report);
    $vanid = $report['vanid'];
    $mcids = $votersObj->getNlId($vanid);
    //nlp_debug_msg('$mcids',$mcids);
    if (!empty($mcids)) {
      reset($mcids);
      $mcid = key($mcids);
      $nl = $nlsObj->getNlById($mcid);
      //nlp_debug_msg('$nl',$nl);
      if(!empty($nl)) {
        $defaultResult['firstName'] = $nl['firstName'];
        $defaultResult['lastName'] = $nl['lastName'];
        $defaultResult['county'] = $nl['county'];
      }
    }
    $defaultResult['mcid'] = $mcid;

    if(!empty($report['dateCanvassed'])) {
      $canvassDate = $report['dateCanvassed'];
      $canvassTime = strtotime($canvassDate);
    } elseif(!empty($report['dateCreated'])) {
      $canvassDate = $report['dateCreated'];
      $canvassTime = strtotime($canvassDate);
    } else {
      $canvassTime = $report['dateTimeCreated'];
    }
    $defaultResult['contactDate'] = date('Y-m-d',$canvassTime) ;
    $defaultResult['vanid'] = $report['vanid'];

    $action['processReport'] = FALSE;
    $action['counts'] = array();
    //nlp_debug_msg('filetype',$fileType);
    switch ($fileType) {

      case 'survey':
        $action = $minivanObj->nlp_process_survey($report,$defaultResult,$surveyQuestions,$cid);
        break;

      case 'canvass':
        $action = $minivanObj->process_canvass($report,$defaultResult,$allowedResponseCodes,$cid);
        break;

      case 'activist':
        $action = $minivanObj->process_activist($report,$defaultResult,$mcid,$nlpHostile,$cid);
        break;

      case 'note':
        $action = $minivanObj->process_note($report, $defaultResult);
        break;
    }
    nlp_debug_msg('$action',$action);
    foreach ($action['counts'] as $countType => $value) {
      $counts[$countType] += $value;
    }
    if($action['processReport']) {
      if(!$action['mergeReport']) {
        $rIndex = $nlpReportsObj->setNlReport($action['result']);
      } else {
        $nlpReportsObj->mergeReport($action['result']);
        $rIndex = $action['result']['reportIndex'];
        nlp_debug_msg('$rIndex',$rIndex);
      }
      // Mark Contact attempt for this voter.
      $turfVoter = array();
      $turfVoter['vanid'] = $vanid;
      $turfVoter['mcid'] = $mcid;
      if(!empty($action['type'])) {
        $turfVoter[$action['type']] = $rIndex;
      }
      foreach ($mcids as $mcid => $turfIndexes) {
        $nl = $nlsObj->getNlById($mcid);
        //nlp_debug_msg('$nl',$nl);
        $turfVoter['county'] = $nl['county'];
        foreach ($turfIndexes as $turfIndex) {
          $turfVoter['turfIndex'] = $turfIndex;
          nlp_debug_msg('$turfVoter',$turfVoter);
          $votersObj->updateTurfVoter($turfVoter);
        }
        // This NL has reported results.
        $nlsObj->resultsReported($mcid,$nl['county']);
      }
    }
  }
  return $counts;
}

/** * * * * * * * * * * * * * * * * * * * * * * * * * * * * * *
 * importMinivanBatch
 *
 * Read the provided file and save the Dems.
 *
 * @param $arg
 * @param $context
 * @noinspection PhpUnused
 */
function importMinivanBatch($arg,&$context) {
  //nlp_debug_msg('arg',$arg);
  $fileType = $arg['fileType'];
  $subject = $arg['subject'];
  $month = $arg['month'];
  $day = $arg['day'];
  $result = recordMinivanReports($arg['records'],$fileType);
  $context['finished'] = 1;
  $context['results']['subject'] = $subject;
  $context['results']['month'] = $month;
  $context['results']['day'] = $day;
  $context['results']['counts'] = $result;
}

/** * * * * * * * * * * * * * * * * * * * * * * * * * * * * * *
 * importMinivanFinished
 *
 * The batch operation is finished.  Report the results.
 *
 * @param $success
 * @param $results
 * @param $unused
 * @noinspection PhpUnusedParameterInspection
 */
function importMinivanFinished($success, $results, $unused) {
  $messenger = Drupal::messenger();

  if ($success) {

    // Report results.
    $subject = $results['subject'];
    $month = $results['month'];
    $day = $results['day'];
    $counts = $results['counts'];
    $messenger->addStatus($counts['recordCnt'].' records processed from email, processed: '.
      $counts['processedCnt'].', duplicates: '.$counts['duplicateCnt'].
      ' - subject: '.$subject.'  Date: '.$month.''. $day);
    $messenger->addStatus('The MiniVAN reports successfully updated.');
  }
  else {
    $messenger->addError(t('An error occurred.'));
  }
}
