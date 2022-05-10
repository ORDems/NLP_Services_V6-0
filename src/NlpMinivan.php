<?php

namespace Drupal\nlpservices;

use Drupal;
use Drupal\Core\Config\ConfigFactoryInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;


class NlpMinivan {

  const MINIVAN_MAX_QUEUE = 200;

  private array $minivanSurveyHdr = array(
    'vanid' => array('name'=>'myv_van_id','err'=>'myv_van_id'),
    'dateCanvassed' => array('name'=>'date_canvassed','err'=>'date_canvassed'),
    'dateCreated' => array('name'=>'date_created','err'=>'date_created'),
    'inputTypeId' => array('name'=>'input_type_id','err'=>'input_type_id'),
    'surveyQuestionId' => array('name'=>'survey_question_id','err'=>'survey_question_id'),
    'surveyResponseId' => array('name'=>'survey_response_id','err'=>'survey_response_id'),
    'contactId' => array('name'=>'contacts_contact_id','err'=>'contacts_contact_id'),
  );

  private array $minivanCanvassHdr = array(
    'vanid' => array('name'=>'myv_van_id','err'=>'myv_van_id'),
    'dateCanvassed' => array('name'=>'date_canvassed','err'=>'date_canvassed'),
    'dateCreated' => array('name'=>'date_created','err'=>'date_created'),
    'inputTypeId' => array('name'=>'input_type_id','err'=>'input_type_id'),
    'contactTypeId' => array('name'=>'contact_type_id','err'=>'contact_type_id'),
    'resultId' => array('name'=>'result_id','err'=>'result_id'),
    'contactId' => array('name'=>'contacts_contact_id','err'=>'contacts_contact_id'),
  );

  private array $minivanActivistHdr = array(
    'vanid' => array('name'=>'myv_van_id','err'=>'myv_van_id'),
    'dateCreated' => array('name'=>'date_created','err'=>'date_created'),
    'activistCodeId' => array('name'=>'activist_code_id','err'=>'activist_code_id'),
    'contactId' => array('name'=>'contacts_activist_code_id','err'=>'contacts_activist_code_id'),
  );

  private array $minivanNoteHdr = array(
    'vanid' => array('name'=>'myv_van_id','err'=>'myv_van_id'),
    'dateTimeCreated' => array('name'=>'datetime_created','err'=>'datetime_created'),
    'noteText' => array('name'=>'note_text','err'=>'note_text'),
    'noteId' => array('name'=>'contacts_note_id','err'=>'contacts_note_id'),
    'contactId' => array('name'=>'contacts_contact_id','err'=>'contacts_contact_id'),
  );


  private NlpReports $nlpReportsObj;
  private NlpVoters $voterObj;
  private NlpNls $nlsObj;
  protected ConfigFactoryInterface $config;

  public function __construct( $config, $nlpReportsObj, $voterObj,  $nlsObj) {
    $this->config = $config;
    $this->nlpReportsObj = $nlpReportsObj;
    $this->voterObj = $voterObj;
    $this->nlsObj = $nlsObj;

  }

  public static function create(ContainerInterface $container): NlpMinivan
  {
    return new static(
      $container->get('config.factory'),
      $container->get('nlpservices.reports'),
      $container->get('nlpservices.voters'),
      $container->get('nlpservices.nls'),

    );
  }


  private function decodeMinivanHdr($fileHdr,$requiredFields): array
  {
    //nlp_debug_msg('header', $fileHdr);
    $hdrErr = array();
    $hdrPos = array();
    foreach ($requiredFields as $nlpKey => $vanField) {
      $found = FALSE;
      foreach ($fileHdr as $fileCol=>$fileColName) {
        if($fileColName == trim($vanField['name'])) {
          $hdrPos[$nlpKey] = $fileCol;
          $found = TRUE;
          break;
        }
      }
      if(!$found) {
        $hdrErr[] = 'The MyCampaign export option "'.$vanField['err'].'" is missing.';
      }
    }
    $fieldPos['pos'] = $hdrPos;
    $fieldPos['err'] = $hdrErr;
    $fieldPos['ok'] = empty($hdrErr);
    return $fieldPos;
  }

  public function decodeMinivanSurveyHdr($fileHdr): array
  {
    return $this->decodeMinivanHdr($fileHdr, $this->minivanSurveyHdr);
  }

  public function decodeMinivanCanvassHdr($fileHdr): array
  {
    return $this->decodeMinivanHdr($fileHdr, $this->minivanCanvassHdr);
  }

  public function decodeMinivanActivistHdr($fileHdr): array
  {
    return $this->decodeMinivanHdr($fileHdr, $this->minivanActivistHdr);
  }

  public function decodeMinivanNoteHdr($fileHdr): array
  {
    return $this->decodeMinivanHdr($fileHdr, $this->minivanNoteHdr);
  }

  public function extractMinivanFields($record,$hdrPos): array
  {
    $fields = array();
    foreach ($hdrPos as $fieldName => $pos) {
      $fields[$fieldName] = $record[$pos];
    }
    return $fields;
  }

  /** @noinspection PhpUnused */
  public function nlp_process_survey($report,$result,$questions,$cid): array
  {
    $contactId = $report['contactId'];
    if($this->nlpReportsObj->reportExists($contactId)) {
      $action['processReport'] = FALSE;
      $action['counts']['duplicateCnt'] = 1;
      //nlp_debug_msg('$contactId',$contactId);
      return $action;
    }
    $qid = $report['surveyQuestionId'];
    $rid = $report['surveyResponseId'];
    if(empty($questions)) {
      $action['processReport'] = FALSE;
      $action['counts']['rejectedCnt'] = 1;
      //nlp_debug_msg('$questions',$questions);
      return $action;
    }
    if($questions['state']['surveyQuestionId'] == $qid) {
      $title = $questions['state']['name'];
      $surveyResponseList = $questions['state']['responses'];
    } else {
      $action['processReport'] = FALSE;
      $action['counts']['rejectedCnt'] = 1;
      return $action;
    }

    $response = '';
    if(!empty($surveyResponseList)) {
      if(!empty($surveyResponseList[$rid])) {
        $response = $surveyResponseList[$rid]['mediumName'];
      }
    }
    $result['type'] = 'Survey';
    $result['value'] = $response;
    $result['text'] = $title;
    $result['qid'] = $qid;
    $result['rid'] = $rid;
    $result['active'] = TRUE;
    $result['contactType'] = 'Walk';
    $result['cid'] = $cid;
    $result['contactId'] = $contactId;

    $action['processReport'] = TRUE;
    $action['mergeReport'] = FALSE;
    $action['type'] = 'pledgedToVote';
    $action['result'] = $result;
    $action['counts']['processedCnt'] = 1;
    return $action;
  }

  /** @noinspection PhpUnused */
  public function process_canvass($report, $result, $allowedResponseCodes, $cid): array
  {
    $nlpConfig = $this->config->get('nlpservices.configuration');
    $electionDates = $nlpConfig->get('nlpservices-election-configuration');
    $cycle = $electionDates['nlp_election_cycle'];

    $contactId = $report['contactId'];
    $reportExists = $this->nlpReportsObj->reportExists($contactId);
    //nlp_debug_msg('$reportExists',$reportExists);
    if($reportExists) {
      $action['processReport'] = FALSE;
      $action['counts']['duplicateCnt'] = 1;
      //nlp_debug_msg('$action',$action);
      return $action;
    }
    $vanid = $report['vanid'];
    $rid = $report['resultId'];
    $contactTypeId = $report['contactTypeId'];

    $knownContactTypeName = $knownResponse = '';
    foreach ($allowedResponseCodes as $contactTypeName=>$responseCode) {
      if($responseCode['code'] == $contactTypeId) {
        if(in_array($rid,$responseCode['responses'])) {
          $knownContactTypeName = $contactTypeName;
          //$allowedResponses = $responseCode['responses'];
          $knownResponses = array_flip($responseCode['responses']);
          $knownResponse = $knownResponses[$rid];
        }
        break;
      }
    }

    if(empty($knownContactTypeName)) {
      $action['processReport'] = FALSE;
      $action['counts']['rejectedCnt'] = 1;
      //nlp_debug_msg('$action',$action);
      return $action;
    }

    $result['active'] = TRUE;
    $result['contactType'] = 'Walk';
    $result['cid'] = $cid;
    $result['contactId'] = $contactId;
    $result['type'] = 'contact';
    $result['value'] = $knownResponse;
    $result['text'] = '';
    $result['qid'] = NULL;
    $result['rid'] = $rid;
    $result['rIndex'] = NULL;

    //nlp_debug_msg('$knownResponse',$knownResponse);
    switch ($knownResponse) {
      case 'Not Home':
      case 'Refused':
      case 'Left Message/Lit':
        break;

      case 'Moved':
        $request['vanid'] = $vanid;
        $request['type'] = 'contact';
        $request['value'] = 'moved';
        $request['cycle'] = $cycle;
        $report = $this->nlpReportsObj->getReport($request);
        //nlp_debug_msg('$report',$report);
        $existingRIndex = (!empty($report['reportIndex']))?$report['reportIndex']:NULL;  // An entry for the Moved status exists.
        if(!empty($existingRIndex)) {
          $action['processReport'] = FALSE;
          return $action;
        }
        $voterAddresses = $this->voterObj->getVoterAddresses($vanid);
        //nlp_debug_msg('$voterAddresses',$voterAddresses);
        $addressEncode = array();
        foreach ($voterAddresses as $voterAddress) {
          $simpleAddress = $this->voterObj->addressExtract($voterAddress);
          $addressEncode = json_encode($simpleAddress);
          $movedStatus = 1;
          $turfIndex = $voterAddress['turfIndex'];
          //nlp_debug_msg('$turfIndex',$turfIndex);
          //nlp_debug_msg('$vanid',$vanid);
          //nlp_debug_msg('$movedStatus',$movedStatus);
          $this->voterObj->setMovedStatus($turfIndex,$vanid,$movedStatus);
        }

        $result['rIndex'] = NULL;
        $result['value'] = 'moved';
        $result['text'] = $addressEncode;
        $action['processReport'] = TRUE;
        $action['mergeReport'] = FALSE;
        $action['result'] = $result;
        $action['type'] = 'attemptedContact';
        $action['counts']['processedCnt'] = 1;
        return $action;

      case 'Deceased':

        // Check if this voter has already been reported as deceased.
        $request['vanid'] = $vanid;
        $request['type'] = 'contact';
        $request['value'] = 'deceased';
        $request['cycle'] = $cycle;
        $report = $this->nlpReportsObj->getReport($request);
        //nlp_debug_msg('report',$report);

        $existingRIndex = (!empty($report['reportIndex']))?$report['reportIndex']:NULL;  // An entry for the Deceased status exists.
        if(!empty($existingRIndex)) {
          /*
          $resultInactive['reportIndex'] = $existingRIndex;
          $resultInactive['active'] = 0;
          $this->nlpReportsObj->updateReport($resultInactive);
          */
          $action['processReport'] = FALSE;
          return $action;
        }

        // Remember the state of the NLP Deceased activist code in VoteBuilder.
        $voterStatus = $this->voterObj->getVoterStatus($vanid);
        $voterStatus['deceased'] = 1;
        $this->voterObj->setVoterStatus($vanid, $voterStatus);

        // This voter is being reported as deceased.  Record in NLP Services.
        $result['value'] = 'deceased';
        $action['processReport'] = TRUE;
        $action['mergeReport'] = FALSE;
        $action['type'] = 'attemptedContact';
        $action['result'] = $result;
        $action['counts']['processedCnt'] = 1;

        return $action;
    }
    $action['processReport'] = TRUE;
    $action['mergeReport'] = FALSE;
    $action['type'] = 'attemptedContact';
    $action['result'] = $result;
    $action['counts']['processedCnt'] = 1;
    return $action;
  }

  /** @noinspection PhpUnused */
  public function process_activist($report, $result, $mcid, $nlpHostile, $cid): array
  {
    //$messenger = Drupal::messenger();
    $nlpConfig = $this->config->get('nlpservices.configuration');
    $electionDates = $nlpConfig->get('nlpservices-election-configuration');
    $cycle = $electionDates['nlp_election_cycle'];

    $contactId = $report['contactId'];
    $exists = $this->nlpReportsObj->reportExists($contactId);
    if($exists) {
      $action['processReport'] = FALSE;
      $action['counts']['duplicateCnt'] = 1;
      return $action;
    }
    //nlp_debug_msg('report',$report);
    $vanid = $report['vanid'];
    $nl = $this->nlsObj->getNlById($mcid);
    //nlp_debug_msg('nl', $nl);
    if(!empty($nl)) {
      $result['firstName'] = $nl['firstName'];
      $result['lastName'] = $nl['lastName'];
      $result['county'] = $nl['county'];
    }
    $result['mcid'] = $mcid;

    $rIndex = $this->nlpReportsObj->getAcReportIndex($vanid, $cycle,'NLPHostile');
    if(!empty($rIndex)) {
      $action['processReport'] = FALSE;
      $action['counts']['processedCnt'] = 1;
    }

    $result['active'] = TRUE;
    $result['reportIndex'] = NULL;
    $result['rid'] = $nlpHostile['activistCodeId'];
    $result['contactType'] = 'Walk';
    $result['type'] = 'Activist';
    $result['value'] = 1;
    $result['text'] = 'NLPHostile';
    $result['cid'] = $cid;
    $result['qid'] = NULL;
    $result['contactId'] = $contactId;
    //nlp_debug_msg('result', $result);
    $this->nlpReportsObj->setNlReport($result);

    // Results reported by this NL.
    $this->nlsObj->resultsReported($mcid,$nl['county']);

    // Remember the state of the NLP Hostile.
    $voterStatus = $this->voterObj->getVoterStatus($vanid);
    $voterStatus['hostile'] = 1;
    $this->voterObj->setVoterStatus($vanid, $voterStatus);

    $action['processReport'] = FALSE;
    $action['counts']['processedCnt'] = 1;
    return $action;
  }

  public function process_note($report,$result): array
  {
    $contactId = $report['contactId'];
    $exists = $this->nlpReportsObj->reportExists($contactId);
    if($exists) {
      $action['processReport'] = FALSE;
      $action['counts']['duplicateCnt'] = 1;
      return $action;
    }
    //nlp_debug_msg('report',$report);
    $vanid = $report['vanid'];

    $noteString = $report['noteText'];
    $commentMax = 190;
    if (strlen($noteString) > $commentMax) {
      $note = substr($noteString,0,$commentMax);  // Truncate the comment.
    } else {
      $note = $noteString;
    }
    $note = str_replace("\r\n", "<br>", $note);

    // Find all the NLs with turf that includes this voter.  Catches duplicate
    // turfs and voters who move.
    $voterAddresses = $this->voterObj->getVoterAddresses($vanid);
    //nlp_debug_msg('addresses',$voterAddresses);
    $nlList = array();
    $mcid = 0;
    foreach ($voterAddresses as $voterAddress) {
      $turfIndex = $voterAddress['turfIndex'];
      $turfMcid = $this->voterObj->getTurfMcid($vanid,$turfIndex);
      if(!empty($turfMcid)) {
        $mcid = $turfMcid;
        $nl = $this->nlsObj->getNlById($turfMcid);
        if(!empty($nl)) {
          $nlTurf['turfIndex'] = $turfIndex;
          $nlList[$turfMcid][$turfIndex] = $nlTurf;
        }
      }
    }
    // With luck, there is only onw turf with this voter.
    // Add a new comment record.
    $result['type'] = 'Comment';
    $result['value'] = '';
    $result['text'] = $note;
    $result['contactId'] = $report['contactId'];
    $result['active'] = TRUE;
    $result['contactType'] = 'Walk';
    $result['cid'] = NULL;
    $result['rid'] = NULL;
    $result['qid'] = NULL;

    if(count($nlList) != 1) {
      $result['mcid'] = $nl['county'] = NULL;
    } else {
      $nl = $this->nlsObj->getNlById($mcid);
      $result['mcid'] = $mcid;
      $result['county'] = $nl['county'];
    }

    // Record the new note.
    //nlp_debug_msg('result',$result);
    $rIndex = $this->nlpReportsObj->setNlReport($result);

    foreach ($nlList as $nlTurfs) {
      foreach ($nlTurfs as $turfIndex=>$nlTurf) {
        $this->voterObj->updateTurfNote($turfIndex,$vanid,$note,$rIndex,$report['noteId']);
      }
    }

    $action['processReport'] = FALSE;
    $action['counts']['processedCnt'] = 1;
    return $action;
  }

  public function header_validate($fileType,$headerRaw): array
  {
    $messenger = Drupal::messenger();
    $headerRecord = trim(strip_tags(htmlentities(stripslashes($headerRaw),ENT_QUOTES)));
    // Extract the column headers.
    $columnHeader = str_getcsv($headerRecord);
    $fieldPos['ok'] = FALSE;

    switch ($fileType) {
      case 'survey':
        $fieldPos = $this->decodeMinivanSurveyHdr($columnHeader);
        break;
      case 'canvass':
        $fieldPos = $this->decodeMinivanCanvassHdr($columnHeader);
        break;
      case 'activist':
        $fieldPos = $this->decodeMinivanActivistHdr($columnHeader);
        break;
      case 'note':
        $fieldPos = $this->decodeMinivanNoteHdr($columnHeader);
        break;
      default:
        $fieldPos['err'][0] = 'Bad file type';
        break;
    }
    if(!$fieldPos['ok']) {
      foreach ($fieldPos['err'] as $errMsg) {
        $messenger->addWarning($errMsg);
      }
    }
    return $fieldPos;
  }

  public function fetch_reports($reportsRaw,$fileType,$activistCode,$pos): array
  {
    //nlp_debug_msg('$pos',$pos);
    //nlp_debug_msg('$reportsRaw',$reportsRaw);
    //nlp_debug_msg('$activistCode',$activistCode);

    $reports = array();
    $blockIndex = 0;
    $recordCount = 0;
    foreach ($reportsRaw as $rowRaw) {
      $fieldsRaw = str_getcsv($rowRaw);
      // Remove any stuff that might be a security risk.
      $record = array();
      foreach ($fieldsRaw as $fieldRaw) {
        //$record[] = nlp_sanitize_string($fieldRaw);
        $record[] = trim(strip_tags(htmlentities(stripslashes($fieldRaw),ENT_QUOTES)));
      }
      //nlp_debug_msg('$record',$record);
      $report = $this->extractMinivanFields($record,$pos);
      $report['fileType'] = $fileType;
      //nlp_debug_msg('report '.$fileType, $report);
      switch ($fileType) {
        case 'survey':
        case 'canvass':
          if(!empty($report['inputTypeId']) AND $report['inputTypeId'] == 14)  { // MiniVAN report.
            $reports[$blockIndex][] = $report;
            $recordCount++;
          }
          break;
        case 'activist':
          //if($report['activistCodeId'] == $activistCode['activistCodeId']) {
          if($report['activistCodeId'] == $activistCode) {
            $reports[$blockIndex][] = $report;
            $recordCount++;
          }
          break;
        case 'note':
          $reports[$blockIndex][] = $report;
          $recordCount++;
          break;
      }
      if($recordCount == $this::MINIVAN_MAX_QUEUE) {
        $blockIndex++;
        $recordCount = 0;
      }
    }
    return $reports;
  }

}