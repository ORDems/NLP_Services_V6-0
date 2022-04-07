<?php

namespace Drupal\nlpservices\Form;

use Drupal;
use Drupal\Core\File\FileSystemInterface;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use stdClass;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\nlpservices\ApiExportJobs;
use Drupal\nlpservices\ApiSavedLists;
use Drupal\nlpservices\ApiFolders;
use Drupal\nlpservices\NlpNls;
use Drupal\nlpservices\ApiVoter;
use Drupal\nlpservices\NlpVoters;
use Drupal\nlpservices\NlpTurfs;
use Drupal\nlpservices\ApiSurveyQuestion;
use Drupal\nlpservices\NlpPaths;
use Drupal\nlpservices\MagicWord;
use Drupal\nlpservices\DrupalUser;
use Drupal\nlpservices\NlpReports;
use Drupal\nlpservices\NlpEncryption;

/**
 * @noinspection PhpUnused
 */
class SyncTurfForm extends FormBase {
  protected ApiExportJobs $exportJobs;
  protected ApiSavedLists $savedLists;
  protected ApiFolders $folders;
  protected NlpNls $nls;
  protected ApiVoter $apiVoter;
  protected NlpVoters $voters;
  protected NlpTurfs $turfs;
  protected ApiSurveyQuestion $surveyQuestion;
  protected NlpPaths $paths;
  protected MagicWord $magicWord;
  protected DrupalUser $drupalUser;
  protected NlpReports $reports;
  protected FileSystemInterface $fileSystem;
  protected NlpEncryption $nlpEncrypt;
  
  
  public function __construct( $exportJobs, $savedLists, $folders, $nls, $apiVoter, $voters, $turfs, $surveyQuestion,
                               $paths, $magicWord, $drupalUser, $reports, $fileSystem, $nlpEncrypt) {
    $this->exportJobs = $exportJobs;
    $this->savedLists = $savedLists;
    $this->folders = $folders;
    $this->nls = $nls;
    $this->apiVoter = $apiVoter;
    $this->voters = $voters;
    $this->turfs = $turfs;
    $this->surveyQuestion = $surveyQuestion;
    $this->paths = $paths;
    $this->magicWord = $magicWord;
    $this->drupalUser = $drupalUser;
    $this->reports = $reports;
    $this->fileSystem = $fileSystem;
    $this->nlpEncrypt = $nlpEncrypt;
  
  }
  
  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container): SyncTurfForm
  {
    return new static(
      $container->get('nlpservices.export_jobs'),
      $container->get('nlpservices.saved_lists'),
      $container->get('nlpservices.folders'),
      $container->get('nlpservices.nls'),
      $container->get('nlpservices.api_voter'),
      $container->get('nlpservices.voters'),
      $container->get('nlpservices.turfs'),
      $container->get('nlpservices.survey_question'),
      $container->get('nlpservices.paths'),
      $container->get('nlpservices.magic_word'),
      $container->get('nlpservices.drupal_user'),
      $container->get('nlpservices.reports'),
      $container->get('file_system'),
      $container->get('nlpservices.encryption'),

    );
  }
  
  /**
   * {@inheritdoc}
   */
  public function getFormId(): string
  {
    return 'nlpservices_sync_active_nls_form';
  }
  
  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state): array
  {
    if (empty($form_state->get('reenter'))) {
      $form_state->set('phase','turf_list_select');
      $factory = Drupal::service('tempstore.private');
      $store = $factory->get('nlpservices.session_data');
      $county = $store->get('County');
      $form_state->set('county',$county);
      
      $config = $this->config('nlpservices.configuration');
      $apiKeys = $config->get('nlpservices-api-keys');
      $committeeKey = $apiKeys[$county];
      $committeeKey['API Key'] = $this->nlpEncrypt->encrypt_decrypt('decrypt', $committeeKey['API Key']);
      $form_state->set('committeeKey',$committeeKey);
      
      $stateCommitteeKey = $apiKeys['State Committee'];
      $stateCommitteeKey['API Key'] = $this->nlpEncrypt->encrypt_decrypt('decrypt', $stateCommitteeKey['API Key']);
      $form_state->set('stateCommitteeKey',$stateCommitteeKey);
      
      $electionDates = $config->get('nlpservices-election-configuration');
      $cycle = $electionDates['nlp_election_cycle'];
      $form_state->set('cycle',$cycle);
      $form_state->set('cycleName',$electionDates['nlp_cycle_name']);
      
      $cycleParts = explode('-',$cycle);
      $cycleYear = $cycleParts[0];
      $form_state->set('cycleYear',$cycleYear);
      
      $countyNames = $config->get('nlpservices-county-names');
      $form_state->set('state',$countyNames['State']);
  
      $nlpHostile = $config->get('nlpservices_hostile_ac');
      $form_state->set('nlpHostile',$nlpHostile);
      //nlp_debug_msg('$nlpHostile',$nlpHostile);

      $nlpVoter = $config->get('nlpservices_voter_ac');
      $form_state->set('nlpVoter',$nlpVoter);
      //nlp_debug_msg('$nlpVoter',$nlpVoter);
    }
    $county = $form_state->get('county');
    
    $phase = $form_state->get('phase');
    //nlp_debug_msg('$phase',$phase);
    switch ($phase) {
      case 'turf_list_select':
        $committeeKey = $form_state->get('committeeKey');
        $folderInfo = $justNames = [];
  
        $currentFolderId = $form_state->get('currentFolderId');
        $form['turf_list_select'] = $this->turf_list_select(
          $county,$committeeKey,$currentFolderId,$folderInfo,$justNames,$listNames);
        $form_state->set('folderInfo', $folderInfo);
        $form_state->set('justNames', $justNames);
        $form_state->set('listNames', $listNames);
  
        // Name of the PDF of the walksheet for the turf.
        $form['turf_list_select']['turf_pdf'] = array(
          '#type' => 'file',
          '#title' => t('NL turf walksheet (Optional)'),
          '#size' => 75,
          '#description' => 'The turf file must be a pdf, xlsx or xls file format.'
        );
        $form['turf_list_select']['help_pdf'] = array(
          '#markup' => '<p><i>While the walksheet is optional, it is a good idea to include it here with the turf.   This <br>
          is especially true if the NL has more than one turf.  The walksheet is not needed for NLs <br>
          that send postcards or use a phone or social media. <br></i></p>'
        );

        $form['turf_list_select']['turf_submit'] = array(
          '#name' => 'turf_submit',
          '#type' => 'submit',
          '#value' => 'Checkin the selected turf >>',
        );
        break;
      case 'nl_select':
        break;
    }
    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function validateForm(array &$form, FormStateInterface $form_state)
  {
    $form_state->set('reenter', TRUE);
    $messenger = Drupal::messenger();
    $values = $form_state->getValues();
    //nlp_debug_msg('validate - $values',$values);

    $triggeringElement = $form_state->getTriggeringElement();
    //nlp_debug_msg('$triggeringElement',$triggeringElement);
    $elementClicked = $triggeringElement['#name'];
    if($elementClicked == 'folder') {
      $form_state->set('currentFolderId', $values['folder']);
    return;
    }
    
    $listId = $form_state->getValue('list_select');
    if(empty($listId)) {
      $form_state->setErrorByName('list_select','You have to select a turf.');
      return;
    }
    $form_state->set('listId', $listId);
    // Now check that we have the PDF for the turf.
    $pdf_file = $_FILES['files']['name']['turf_pdf'];
    $pdf_tmp = $_FILES['files']['tmp_name']['turf_pdf'];
    $file_type_pdf = $pdf_name = '';
    if (!empty($pdf_file)) {
      $file_name_pdf = strtolower($pdf_file);
      $file_name_pdf_array = explode('.', $file_name_pdf);
      $file_type_pdf = end($file_name_pdf_array);
      $allowed = array('pdf','xls','xlsx');
      if (!in_array($file_type_pdf, $allowed)) {
        $form_state->setErrorByName('turf_pdf',
          'The walksheet must be a PDF, XLS, or XLSX type.');
        return;
      }
      $pdf_name = $file_name_pdf_array[0];
    }
    $form_state->set('pdf_file', $pdf_file);
    $form_state->set('pdf_tmp', $pdf_tmp);
    $form_state->set('pdf_name', $pdf_name);
    $form_state->set('pdf_suffix', $file_type_pdf);
  
    $listNames = $form_state->get('listNames');
    //nlp_debug_msg('$listNames',$listNames);
    //nlp_debug_msg('$listId: '.$listId);
    if(empty($listNames[$listId]['listCount'])) {
      $form_state->setErrorByName('list_select', 'The NL tag seems wrong.');
      return;
    }
    
    $listCount = $listNames[$listId]['listCount'];
    if ($listCount > 100) {
      $form_state->setErrorByName('list_select', 'The turf must have 100 voters or less.');
      return;
    } elseif($listCount > 75) {
      $messenger->addWarning('Turfs with more than 75 voters are discouraged');
    }
    if(empty($listNames[$listId])) {
      $form_state->setErrorByName('list_select', 'Unknown NL.');
      nlp_debug_msg('listId: '.$listId, $form_state['nlp']['event']['list_names']);
    }
    if(empty($listNames[$listId]['mcid'])) {
      $form_state->setErrorByName('list_select', 'The NL tag seems wrong.');
      return;
    }
    $mcid = $listNames[$listId]['mcid'];
    $nl = $this->nls->getNlById($mcid);
    if(empty($nl)) {
      $form_state->setErrorByName('list_select', 'The NL tagged for this turf is not in the list of active NLs.');
      return;
    }
    $form_state->set('nl', $nl);
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state)
  {
    $messenger = Drupal::messenger();

    $county = $form_state->get('county');
    $state = $form_state->get('state');
    $nlpVoter = $form_state->get('nlpVoter');
    $stateCommitteeKey = $form_state->get('stateCommitteeKey');
    $cycleYear = $form_state->get('cycleYear');
    $cycle = $form_state->get('cycle');
    $cycleName = $form_state->get('cycleName');
  
    $database = 0;  // VoterFile.
    $listId = $form_state->get('listId');
    
    $listNames = $form_state->get('listNames');
    $listName = $listNames[$listId]['name'];
    
    $nlpHostile = $form_state->get('nlpHostile');
    //$activistCodeObj = new stdClass();
    //$activistCodeObj->activistCodeId = $nlpHostile;
   
    $jobObj = new stdClass();
  
    $committeeKey = $form_state->get('committeeKey');
    $exportJob = $this->exportJobs->getExportJobs($committeeKey,$jobObj,$listId,$county,$database);
    if(empty($exportJob['downloadUrl'])) {
      $messenger->addWarning(t('There are no voters in this file.'));
      return;
    }
    sleep(1);
    for ($index = 0; $index < 5; $index++) {
      $exportJobStatus = $this->exportJobs->getExportJobStatus($exportJob['eventId']);
      if($exportJobStatus != -1) {break;}
      sleep(1);
    }
    $fh = fopen($exportJob['downloadUrl'] , "r");
    $columnHeader = fgetcsv($fh);
    //nlp_debug_msg('hdr', $columnHeader);
    //nlp_debug_msg('$nlpHostile',$nlpHostile);
    $activistCode = [
      'nlpIndex' => 'hostile',
      'activistCodeId' => $nlpHostile,
    ];
    $fieldPos = $this->apiVoter->decodeApiVoterHdr($columnHeader,$activistCode);
    if (!$fieldPos) {
      fclose($fh);
      nlp_debug_msg(t('Something is wrong with the VoterBuilder API.
      Contact the NLP Admin.'));
      return;
    }
    // Get the voters in the turf export file.
    $voters = $this->get_voters($fh,$fieldPos,$state,$committeeKey);
    //nlp_debug_msg('voters', $voters);
    if(empty($voters)) {return;}
    $leg_districts = $this->validate_turf_precinct($voters);
    fclose($fh);
    //nlp_debug_msg('$leg_districts',$leg_districts);
    if ($leg_districts['precinct'] == '') {
      $messenger->addError(t('The turf must be for one precinct.
      This error likely means the saved list is old and the map region should
      be refreshed.'));
      return;
    }
    $nl = $form_state->get('nl');
    $mcid = $nl['mcid'];
    $duplicates = $this->get_duplicates($voters);
    
    if(!empty($duplicates)) {
      $errType = $this->overlap_test($mcid,$county,$duplicates);
      $count = count($duplicates);
      switch ($errType) {
        case 'replacement':
          $errorMessage = 'There are '.$count.' voters in other turfs already
        assigned to '.$nl['nickname'].'.  ';
          $errorMessage .= 'If this is a replacement turf, please delete the
        existing turf first.';
          $messenger->addError($errorMessage);
          break;
        case 'sameCounty':
          if($count==1) {
            $errorMessage = 'There is one voter in another turf in '.$county.
              ' County.  ';
          } else {
            $errorMessage = 'There are '.$count.' voters in other turfs in '.
              $county.' County.  ';
          }
          $errorMessage .= 'Please refresh the older map regions and replace the
        old turfs with a new list.';
          $messenger->addError($errorMessage);
          $voterDisplays = $this->display_names($duplicates);
          foreach ($voterDisplays as $voterDisplay) {
            $messenger->addError($voterDisplay);
          }
          break;
        default:
          if($count==1) {
            $errorMessage = 'There is one voter in this turf that is in a turf
          in another County.  ';
          } else {
            $errorMessage = 'There are '.$count.' voters in this turf that are
          in turfs in another County.  ';
          }
          $errorMessage .= 'Either remove the voters from your list or get the
        other county to refresh the map region and re-sync the turf.';
          $messenger->addError($errorMessage);
          $voterDisplays = $this->display_names($duplicates);
          foreach ($voterDisplays as $voterDisplay) {
            $messenger->addError($voterDisplay);
          }
          break;
      }
      return;
    }
    
    //$votersObj = new NlpVoters();
    $this->voters->lockVoters();
    $turfName = $listName.'_'.date("Y-m-d_H:i:s",time());
    // Create a turf table for this new turf.

    $turf = [
      'county' => $county,
      'mcid' => $mcid,
      'nlFirstName' => $nl['firstName'],
      'nlLastName' => $nl['lastName'],
      'turfName' => $turfName,
      'turfPdf' => NULL,
      'turfHd' => $leg_districts['hd'],
      'turfPrecinct' => $leg_districts['precinct'],
      'electionName' => $cycleName,
      'cycle' => $cycle,
    ];
  
    
    // We have a good turf so insert in database.
    $turfIndex = $this->insert_turf($stateCommitteeKey,$voters,$turf,$cycleYear,$nlpVoter);
    if(empty($turfIndex)) {
      $this->voters->unlockVoters();
      return;
    }
    // Save the PDF where we can find it.
    $pdf_tmp = $form_state->get('pdf_tmp');
    //$turf_pdf_name = '';
    if (!empty($pdf_tmp)) {
      $turf_pdf_name = "MCID".$mcid."_".$form_state->get('pdf_name')
        .'_TI'.$turfIndex.'.'.$form_state->get('pdf_suffix');
      //$pathsObj = new NlpPaths();
      $uri = $this->paths->getPath('PDF',$county).$turf_pdf_name;
      //drupal_move_uploaded_file($pdf_tmp, $uri);
      $this->fileSystem->moveUploadedFile($pdf_tmp, $uri);
  
      //$form_state['nlp']['pdf_file'] = $turf_pdf_name;
      $this->turfs->updateTurfFiles('pdf',$turf_pdf_name,$turfIndex);
    }
    $this->voters->unlockVoters();
    // Build the mailing address file.
    //$mail_file = $this->mailing_list($county,$mcid,$turfIndex); //submit.
    //$form_state['nlp']['mail_file'] = $mail_file;
    //$this->turfs->updateTurfFiles('mail',$mail_file,$turfIndex);
    $magicWord = $this->magicWord->createMagicWord();
    $nls_status = $this->nls->getNlsStatus($mcid,$county);
    $nls_status['signedUp'] = 'Y';
    $nls_status['turfCut'] = 'Y';
    $nls_status['asked'] = 'yes';
    $this->nls->setNlsStatus($nls_status);
    $userInfo = array(
      'firstName' => $nl['nickname'],
      'lastName' => $nl['lastName'],
      'county' => $county,
      'mcid' => $mcid,
      'email' => $nl['email'],
      'phone' => $nl['phone'],
      'magicWord' => $magicWord,
    );
    $newAccount = $this->create_drupal_account($userInfo);  // submit.
    //$newAccount = NULL;
    if($newAccount) {
      $this->magicWord->setMagicWord($mcid,$magicWord);
    }
    $success_msg = $turfName." has been successfully checked in.";
    //$turfObj = new NlpTurfs(NULL);
    $turfs = $this->turfs->turfExists($mcid,$county);
    $turfCount = $turfs['turfCnt'];
    $success_msg .= '<br>'.$nl['nickname']." has ".$turfCount." turf(s) assigned.";
    foreach ($turfs['turfs'] as $turf) {
      $success_msg .= '<br>'. $turf['turfIndex']." ".$turf['commitDate']." ".$turf['turfName'];
    }
    $messenger->addStatus(t($success_msg));
  }

  /** * * * * * * * * * * * * * * * * * * * * * * * * * * * * * *
   * nlp_folder_selected_callback
   *
   * @param $form
   * @param $unused
   * @return mixed
   * @noinspection PhpUnused
   * @noinspection PhpUnusedParameterInspection
   */
  function nlp_folder_selected_callback ($form,$unused) {
    //Rebuild the form to list the NLs in the precinct after the precinct is selected.
    return $form['turf_list_select']['turf_select'];
  }

  /** * * * * * * * * * * * * * * * * * * * * * * * * * * * * * *
   * parse_description
   *
   * @param $description
   * @return int|null
   */
  function parse_description($description) {
    if(empty($description)) {return NULL;}
    $descriptionParts = explode(';', $description);
    $nlTag = explode(':', $descriptionParts[0]);
    //nlp_debug_msg('tag', $nlTag);
    if(trim($nlTag[0]) != 'NL') {return NULL;}
    if(empty($nlTag[1])) {return NULL;}
    $mcid = trim($nlTag[1]);
    if(!is_numeric($mcid)) {return NULL;}
    return $mcid;
  }

  /** * * * * * * * * * * * * * * * * * * * * * * * * * * * * * *
   * turf_list_select
   *
   * @param $county
   * @param $committeeKey
   * @param $currentFolderId
   * @param $folderInfo
   * @param $justNames
   * @param $listNames
   * @return array|null
   */
  function turf_list_select($county,$committeeKey,$currentFolderId,&$folderInfo,&$justNames,&$listNames): ?array
  {
    $messenger = Drupal::messenger();
    
    $database = 0;  // VoterFile.
    $allFolderInfo = $this->folders->getApiFolders($committeeKey,$database,NULL);
    if(empty($allFolderInfo)) {
      $messenger->addWarning(t('There are no shared folders'));
      return NULL;
    }
    //nlp_debug_msg('$allFolderInfo: '.$county, $allFolderInfo);
    
    // Check for the county name in the folder name.
    $folderInfo = array();
    foreach ($allFolderInfo as $aFolderId => $aFolderName) {
      $pos = stripos($aFolderName, $county);
      if ($pos !== false) {
        $folderInfo[$aFolderId] = $aFolderName;
      }
    }
    //nlp_debug_msg('$folderInfo: '.$county, $folderInfo);
  
    reset($folderInfo);
    $firstFolderId = key($folderInfo);
    
    if(empty($currentFolderId)) {
      $currentFolderId = $firstFolderId;
    }

    $form_element['turf_select'] = array(
      '#title' => 'Select a list with the turf you want.',
      '#type' => 'fieldset',
      '#prefix' => '<div id="folder_change_wrapper" >',
      '#suffix' => '</div>',
    );
    $form_element['turf_select']['folder'] = array(
      '#type' => 'select',
      '#title' => t('Select the folder where the turf resides.'),
      '#options' => $folderInfo,
      '#default_value' => $currentFolderId,
      '#ajax' => array (
        'callback' => '::nlp_folder_selected_callback',
        'wrapper' => 'folder_change_wrapper',
      )
    );
    
    $lists = $this->savedLists->getSavedLists($committeeKey,0, $currentFolderId);
    //nlp_debug_msg('$lists', $lists);
    $emptyList = FALSE;
    $nlpTurfNames = [];
    if(empty($lists)) {
      $emptyList = TRUE;
    } else {
      foreach ($lists as $list) {
        $mcid = $this->parse_description($list['description']);
        if(empty($mcid)) {continue;}
        $listId = $list['savedListId'];
        $listName['name'] = $list['name'];
        $listName['savedListId'] = $listId;
        $listName['description'] = $list['description'];
        $listName['listCount'] = $list['listCount'];
        $listName['mcid'] = $mcid;
        //nlp_debug_msg('$listName: '.$listId,$listName);
        $nlpTurfNames[$listId] = $listName;
      }
      if(empty($nlpTurfNames)) {
        $emptyList = TRUE;
      }
    }
    //nlp_debug_msg('$nlpTurfNames',$nlpTurfNames);
    array_multisort($nlpTurfNames);
    $listNames = [];
    foreach ($nlpTurfNames as $nlpTurfName) {
      $listNames[$nlpTurfName['savedListId']] = $nlpTurfName;
    }
  
  
    if($emptyList) {
      $form_element['turf_select']['no_lists'] = array(
        '#markup' => t('<p>There are no lists saved with an NL tag in this folder.</p>'),
      );
      return $form_element;
      
    }
    $options = array();
    foreach ($listNames as $listName) {
      $options[$listName['savedListId']] = '<b>Name: </b>'.$listName['name'].
        ' <b>Description:</b> '.$listName['description'].' <b>NL Count:</b> '.
        $listName['listCount'];
    }
    //nlp_debug_msg('$options',$options);
    $justNames = array();
    foreach ($listNames as $listName) {
      $justNames[$listName['savedListId']] = $listName;
    }

    $form_element['turf_select']['list_select'] = array(
      '#title' => t('Select file with list of NLs.'),
      '#type' => 'radios',
      //'#default_value' => $firstListId,
      '#options' => $options,
    );
    
    return $form_element;
    
  }

  /** * * * * * * * * * * * * * * * * * * * * * * * * * * * * * *
   * get_voters
   *
   * @param $fh
   * @param $fieldPos
   * @param $state
   * @param $committeeKey
   * @return array|null
   */
  function get_voters($fh,$fieldPos,$state,$committeeKey): ?array
  {
    $voters = array();

    do {
      $voterRaw = fgetcsv($fh);
      if (!$voterRaw) {break;}  // We've processed the last voter.
      $importedVoter = $this->apiVoter->decodeApiVoterRecord($voterRaw,$fieldPos);

      $vanid = $importedVoter['vanid'];
      $voteBuilderVoter = $this->apiVoter->getApiVoter($committeeKey,0,$vanid);
      //nlp_debug_msg('$voteBuilderVoter',$voteBuilderVoter);
      if(empty($voteBuilderVoter)) {
        nlp_debug_msg('Something is wrong.  Contact NLP Support,  VANID: '.$vanid, '');
        return NULL;
      }
      $voter = array_merge($importedVoter, $voteBuilderVoter);
      //nlp_debug_msg('$voter',$voter);
      // If the nickname field exists, and it has a value for this voter, use it.
      if(empty($voter['nickname'])) {
        $voter['nickname'] = $voter['firstName'];
      }
      
      // Remove leading zeros from HD number.
      $voter['address']['hd'] = ltrim($voter['address']['hd'],'0');
      
      // For Oregon, remove the county from the precinct name.
      if($state == 'Oregon') {
        $voterPrecinct = $voter['address']['precinct'];
        $precinctParts = explode('-', $voterPrecinct);
        if(!empty($precinctParts[1])) {
          $precinctName =  $precinctParts[1];
        } else {
          $precinctName =  $precinctParts[0];
        }
      } else {
        $precinctName = str_replace(' ', '', $voter['address']['precinct']); // Remove blanks.
      }
      $voter['address']['precinct'] = $precinctName;
      //nlp_debug_msg('state: '.$state.', precinct: '.$precinctName);
      
      // Protect against the space in Hood River.
      $voter['address']['county'] = str_replace(' ', '_', $voter['address']['county']); // Remove blanks.
      //nlp_debug_msg('voter', $voter);
      $voters[$voter['vanid']] = $voter;
    } while (TRUE);
    
    return $voters;
  }
  
  /** * * * * * * * * * * * * * * * * * * * * * * * * * * * * * *
   * validate_turf_precinct
   *
   * Verify that we have a turf with voters from just one precinct.
   *
   * @param  $voters - array of voters in the new turf.
   * @return array with precinct and HD number.  Precinct number will
   *          be an empty string if there are more than one precinct
   *          in the list of voters.  The count of voters is also returned.
   */
  function validate_turf_precinct($voters): array
  {
    //nlp_debug_msg('$voters',$voters);
    $turfHd = '';
    $precinctList = array();
    foreach ($voters as $voter) {
      $voterPrecinct = $voter['address']['precinct'];
      $voterHd = $voter['address']['hd'];
      if(!empty($precinctList[$voterPrecinct])) {
        $precinctList[$voterPrecinct]['count']++;
      } else {
        $precinctList[$voterPrecinct]['count'] = 1;
        $precinctList[$voterPrecinct]['hd'] = $voterHd;
      }
    }
    $turfPrecinct = '';
    $turfMaxPrecinctCount = 0;
    foreach ($precinctList as $precinct => $precinctValues) {
      if($precinctValues['count'] > $turfMaxPrecinctCount) {
        $turfPrecinct = $precinct;
        $turfHd = $precinctValues['hd'];
        $turfMaxPrecinctCount = $precinctValues['count'];
      }
    }

    // Return the precinct and HD for this turf.
    $legDistrict['hd'] = $turfHd;
    $legDistrict['precinct'] = $turfPrecinct;
    $legDistrict['count'] = count($voters);
    return $legDistrict;
  }

  /** * * * * * * * * * * * * * * * * * * * * * * * * * * * * * *
   * get_duplicates
   *
   * Check if any voters in the new turf are already assigned to a turf.
   *
   * @param  $voters - list of voters proposed for the new turf.
   *
   * @return array - array of voter info for overlapped voters if any.
   */
  function get_duplicates($voters): array
  {
    // Create an array of vanids from the turf being added for the SQL search.
    $vanids = array_keys($voters);
    // Get all the existing grp records for any voter in the new turf.
    $dupVoterTurfs = $this->voters->duplicateVoters($vanids);
    // If none, exit as we don't have a problem.
    if(empty($dupVoterTurfs)) {return array();}
    // Now get the voter information for all the overlapped voters.
    $dupVanids = array_keys($dupVoterTurfs);
    $dupVoters = $this->voters->getVotersInList($dupVanids);
    foreach ($dupVanids as $vanid) {
      $dupVoters[$vanid]['mcid'] = $dupVoterTurfs[$vanid]['mcid'];
      $dupVoters[$vanid]['turfIndex'] = $dupVoterTurfs[$vanid]['turfIndex'];
      $dupVoters[$vanid]['county'] = $dupVoterTurfs[$vanid]['county'];
    }
    return $dupVoters;
  }

  /** * * * * * * * * * * * * * * * * * * * * * * * * * * * * * *
   * overlap_test
   *
   * Determine the kind of overlap to help create a meaningful message
   * to the coordinator as to how to resolve the overlap.
   *
   * @param  $mcid - The NL for the new turf.
   * @param  $county - The county attempting a check in.
   * @param $duplicates
   * @return string|string[]
   */
  function overlap_test($mcid,$county,$duplicates) {
    if(empty($duplicates)) {return array('err'=>'none');}
    $replacement = TRUE;
    foreach ($duplicates as $dupVoter) {
      if($dupVoter['mcid'] != $mcid) {
        $replacement = FALSE;
        break;
      }
    }
    if($replacement) {
      return 'replacement';
    }
    $sameCounty = TRUE;
    foreach ($duplicates as $dupVoter) {
      if($dupVoter['county'] != $county) {
        $sameCounty = FALSE;
        break;
      }
    }
    if($sameCounty) {
      return 'sameCounty';
    }
    $count = count($duplicates);
    if($count>4) {
      return 'tooMany';
    }
    return 'some';
  }

  /** * * * * * * * * * * * * * * * * * * * * * * * * * * * * * *
   * display_names
   *
   * Display the list of names of voters that overlap with existing turfs.  .
   *
   * @param  $duplicateVoters - array of voter names.
   * @return array
   */
  function display_names($duplicateVoters): array
  {
    $display = array();
    foreach ($duplicateVoters as $vanid => $duplicateVoter) {
      $mcid = $duplicateVoter['mcid'];
      $turfIndex = $duplicateVoter['turfIndex'];
      $turf = $this->turfs->getTurf($turfIndex);
      $turfName = ', Turf: '.$turf['turfName'];
      $nl = $this->nls->getNlById($mcid);
      $nlDisplay = ', NL: '.$nl['nickname'].' '.$nl['lastName'].' County: '
        .$nl['county'].' HD: '.$nl['hd'].' Precinct: '.$nl['precinct'];
      $voterName = 'VANID: '.$vanid.', Voter: '.$duplicateVoter['firstName'].' '
        .$duplicateVoter['lastName'];
      $display[$vanid] = $voterName.$nlDisplay.$turfName;
    }
    return $display;
  }
  
  /** * * * * * * * * * * * * * * * * * * * * * * * * * * * * * *
   * insert_turf
   *
   * Enter the turf into the MySQL table for voters.
   *
   * @param $StateCommitteeKey
   * @param $votersInTurf
   * @param $turf
   * @param $cycle
   * @param $nlpVoterAC
   * @return int
   */
  function insert_turf($StateCommitteeKey,$votersInTurf,$turf,$cycle,$nlpVoterAC): int
  {
    // Set common fields for setting the AC.
    $surveyResponse['type'] = 'Activist';
    $surveyResponse['contactType'] = $this->surveyQuestion::CONTACT_TYPE_WALK;
    $surveyResponse['dateCanvassed'] = NULL;
    $surveyResponse['action'] = 1;
    $surveyResponse['rid'] = $nlpVoterAC;
    
    // Get the names for the ACs managed by NLP; Hostile, and NLP Voter.
    $activistCodeNames = $this->voters->getActivistCodeNames();
    //nlp_debug_msg('$activistCodeNames',$activistCodeNames);
    
    // Create a turf record and get the index for that record.
    $turfIndex = $this->turfs->createTurf($turf);

    // Add each voter into the turf.
    //nlp_debug_msg('$voters',$votersInTurf);
    foreach ($votersInTurf as $vanid => $voter) {
      //nlp_debug_msg('$voter',$voter);
  
      // Update voter status based on imported values of the Activist Codes.
      $voterStatus = $this->voters->getVoterStatus($vanid);
      if(empty($voterStatus['vanid'])) {
        $voterStatus['vanid'] = $vanid;
        $voterStatus['cycle'] = $cycle;
      }
      // Check if the status is from a different election.  Clear a stale status.
      
      if(!empty($voterStatus) AND $voterStatus['cycle'] != $cycle) {
        $voterStatus = $this->voters->nullVoterStatus();
        $voterStatus['vanid'] = $vanid;
        $voterStatus['cycle'] = $cycle;
      }
      //nlp_debug_msg('activistCodes',$voter['activistCodes']);
      foreach ($activistCodeNames as $activistCodeName) {
        $voterStatus[$activistCodeName] = (!empty($voter['activistCodes'][$activistCodeName]))?1:0;
      }
      // Mark this voter as being assigned to an NL.
      $surveyResponse['vanid'] = $vanid;
      $surveyResponse['rid'] = $nlpVoterAC;
     
      $this->surveyQuestion->setApiSurveyResponse($StateCommitteeKey,$surveyResponse);
      
      // Add voter to turf.
      $voter['address']['turfIndex'] = $turfIndex;
      $this->voters->createVoter($voter);
      
      //nlp_debug_msg('$voterStatus: '.$vanid,$voterStatus);
      
      $this->voters->setVoterStatus($vanid, $voterStatus);
      $turfVoter['mcid'] = $turf['mcid'];
      $turfVoter['county'] = $turf['county'];
      $turfVoter['turfIndex'] = $turfIndex;
      $turfVoter['vanid'] = $vanid;
      $turfVoter['cycle'] = $cycle;
      $this->voters->createTurfVoter($turfVoter);
      
      // Restore the information we already know about this voter.
      $this->reload_voter_status($vanid,$voter,$turf['mcid']);
    }
    
    return $turfIndex;
  }
  
  /** * * * * * * * * * * * * * * * * * * * * * * * * * * * * * *
   * mailing_list
   *
   * Create a mailing address list for each household.   The names and ages of
   * every voter in the household will be listed to help address the postcard.
   *
   * @param $county
   * @param $mcid
   * @param $turf_index
   * @return false|string - File name where mail list is saved.
   *                        - FALSE if database error.
   * @noinspection PhpUnused
   */
  function mailing_list($county,$mcid,$turf_index) {
    $messenger = Drupal::messenger();
    //$voterObj = new NlpVoters();
    // Get the list of voters for this turf from the voter grp table, order by voting address.
    $vtr_list = $this->voters->fetchVotersByTurf($turf_index);
    // Create a postcard address file.
    $mail_file = "MAIL_".$mcid."_".$turf_index.".txt";
    //$pathsObj = new NlpPaths();
    $call_path = $this->paths->getPath('MAIL',$county);
    $mail_file_name = $call_path . $mail_file;
    //nlp_debug_msg('$mail_file_name',$mail_file_name);
    file_save_data('', $mail_file_name, FileSystemInterface::EXISTS_REPLACE);
    $mail_fh = fopen($mail_file_name,"w");
    if (empty($mail_fh)) {
      $messenger->addError(t('Failed to open Mail file'));
      return FALSE;
    }
    // Write a header record to the file.
    $hdr_string = "Name(s)"."\t"."Mailing Address"."\n";
    fwrite($mail_fh,$hdr_string);
    // Create the display of voter's mailing address, grouped if more than one at the same address.
    foreach ($vtr_list as $vtr_info) {
      // Extracted the name, address and age info from the vtr record.
      $vtr_sal = " [".$vtr_info['nickname']."]";
      $vtr_nm = $vtr_info['firstName']." ".$vtr_info['lastName'];
      $vtr_age = "- Age(".$vtr_info['age'].")";
      $vtr_name = $vtr_nm.$vtr_sal.$vtr_age;
      if(empty($vtr_info['address']['mAddress'])) {
        $vtr_mail_address = 'Not available.';
      } else {
        $vtr_mail_address = $vtr_info['address']['mAddress'].'<br>'.$vtr_info['address']['mCity']. ', '
          .$vtr_info['address']['mState'].' '.$vtr_info['address']['mZip'];
      }
      //nlp_debug_msg('$vtr_mail_address',$vtr_mail_address);
      // If the first voter in household, remember name and address in case there are others.
      if (empty($current['address'])) {
        $current['address'] = $vtr_mail_address;
        $current['name'] = $vtr_name;
      } else {
        // If not the first voter in the household, then if another voter at the
        // same address, then add the name to the list.
        if($vtr_mail_address == $current['address']) {
          $current['name'] .= "<br>".$vtr_name;
        } else {
          // If this voter is registered at a different address, write the
          // mailing address record, and start over with this voter.
          $mail_string = $current['name']."\t".$current['address']."\n";
          fwrite($mail_fh,$mail_string);
          $current['address'] = $vtr_mail_address;
          $current['name'] = $vtr_name;
        }
      }
    }
    // Write the record for the last household.
    if (!empty($current['address'])) {
      $mail_string = $current['name']."\t".$current['address']."\n";
      fwrite($mail_fh,$mail_string);
    }
    // close the file.
    fclose($mail_fh);
    return $mail_file;
  }
  
  /** * * * * * * * * * * * * * * * * * * * * * * * * * * * * * *
   * create_drupal_account
   *
   * @param $userInfo
   * @return bool
   */
  function  create_drupal_account($userInfo): bool
  {
    $messenger = Drupal::messenger();
    $serverName = $GLOBALS['_SERVER']['SERVER_NAME'];
    $serverNameParts = explode('.',$serverName);
    if(empty($serverNameParts[2])) {  // probably  a MAC.
      $emailServer = $serverNameParts[0].'.'.$serverNameParts[1];
    } else {
      $emailServer = $serverNameParts[1].'.'.$serverNameParts[2];
    }
    //$userObj = new NlpDrupalUser();
    //$query = new EntityFieldQuery();
    $user = $this->drupalUser->getUserByMcid($userInfo['mcid']);
    $msgDisplay = '';
    if(empty($user)) {
      $msgDisplay .= 'This NL does not have an account to use to get the turf. '
        . ' An account will be created.';
      //nlp_set_msg('This NL does not have an account to use to get the turf. '
      //        . ' An account will be created.','status');
      
      $lcUsrName = strtolower($userInfo['firstName'].'.'.$userInfo['lastName']);
      $userName = preg_replace('/-|\s+|&#0*39;|\'/', '', $lcUsrName);

      if(empty($userInfo['email'])) {
        $email = 'do_not_email_'.$userInfo['firstName'].'_'.$userInfo['lastName'].'@'.$emailServer;
      } else {
        $email = $userInfo['email'];
      }

      $account = array(
        'userName' => $userName,
        'email' => $email,
        'firstName' => $userInfo['firstName'],
        'lastName' => $userInfo['lastName'],
        'phone' => $userInfo['phone'],
        'county' => $userInfo['county'],
        'mcid' => $userInfo['mcid'],
        'magicWord' => $userInfo['magicWord'],
        'sharedEmail' => NULL,
        'roles' => array(
          NLP_LEADER_ROLE_ID => NLP_LEADER_ROLE_LABEL,
        ),
      );
      //nlp_debug_msg('account', $account);
      $newUser = $this->drupalUser->addUser($account);
      $newUserMsg = '';
      switch ($newUser['status']) {
        case 'error':
          $newUserMsg = 'Something went wrong with creating an account.  '
            . 'Please contact NLP tech support';
          break;
        case 'exists':
          $newUserMsg = "The Neighborhood Leader's name is already in use.  "
            . 'Please contact NLP tech support';
          break;
        case 'complete':
          $newUserMsg = 'An account was created for this NL.'
            . '<br>Username: '.$newUser['userName']
            . '<br>Password: '.$userInfo['magicWord'];
          break;
      }
      if(!empty($newUserMsg) AND !empty($msgDisplay)) {
        $msgDisplay .= '<br>';
      }
      $msgDisplay .= $newUserMsg;
      if(!empty($msgDisplay)) {
        $messenger->addStatus(t($msgDisplay));
      }
      
      return TRUE;
      
    } else {
      $fieldCheck = array('mcid'=>$userInfo['mcid'],'email'=>$userInfo['email'],'phone'=>$userInfo['phone'],
        'county'=>$userInfo['county'],'firstName'=>$userInfo['firstName'],'lastName'=>$userInfo['lastName']);
      $updateUser = FALSE;
      $nameChanged = $emailChanged = FALSE;
      //nlp_debug_msg('fields', $fieldCheck, __FILE__, __LINE__);
      //nlp_debug_msg('user', $user, __FILE__, __LINE__);
      $update['uid'] = $user['uid'];
      foreach ($fieldCheck as $nlpKey => $nlpValue) {
        //nlp_debug_msg('user: '.$user[$nlpKey].' value: '.$nlpValue, '', __FILE__, __LINE__);
        if($user[$nlpKey] != $nlpValue){
          $updateUser = TRUE;
          switch ($nlpKey) {
            case 'mcid':
              $update['mcid'] = $nlpValue;
              $messenger->addStatus(t("The VANID for this NL was changed from ".$user[$nlpKey]." to ".$nlpValue));
              break;
            case 'email':
              $update['mail'] = $nlpValue;
              $emailChanged = TRUE;
              $messenger->addStatus(t("The email address for this NL was changed from ".$user[$nlpKey]." to ".$nlpValue));
              break;
            case 'phone':
              $update['phone'] = $nlpValue;
              $messenger->addStatus(t("The phone number for this NL was changed from ".$user[$nlpKey]." to ".$nlpValue));
              break;
            case 'county':
              $update['county'] = $nlpValue;
              $messenger->addStatus(t("The county for this NL was changed from ".$user[$nlpKey]." to ".$nlpValue));
              break;
            case 'firstName':
              $update['firstName'] = $nlpValue;
              $nameChanged = TRUE;
              $messenger->addStatus(t("The first name of this NL was changed from ".$user[$nlpKey]." to ".$nlpValue));
              break;
            case 'lastName':
              $update['lastName'] = $nlpValue;
              $nameChanged = TRUE;
              $messenger->addStatus(t("The last name of this NL was changed from ".$user[$nlpKey]." to ".$nlpValue));
              break;
          }
        }
      }
      if($nameChanged) {
        $messenger->addWarning(t("A name change was made for this NL but the username "
          . "for the login was not changed,  Contact the NLP tech support "
          . "to change the login."));
      }
      if($emailChanged) {
        if(empty($update['firstName'])) {
          $update['firstName'] = $user['firstName'];
        }
        if(empty($update['lastName'])) {
          $update['lastName'] = $user['lastName'];
        }
      }
      if($updateUser) {
        $this->drupalUser->updateUser($update);
      }
      
      //$magicWordObj = new NlpMagicWord();
      $magicWord = $this->magicWord->getMagicWord($user['mcid']);
      // The password is lost, create a replacement.
      if(empty($magicWord)) {
        $replacementMagicWord = $this->magicWord->createMagicWord();
        $this->magicWord->setMagicWord($userInfo['mcid'],$replacementMagicWord);
      }
      $messenger->addStatus(t('An account exists for this NL.'
        . '<br>Username: '.$user['userName']
        . '<br>Password: '.$magicWord));
      return FALSE;
    }
  }
  
  /** * * * * * * * * * * * * * * * * * * * * * * * * * * * * * *
   * reload_voter_status
   *
   * Recover the information known about this voter and rebuild the status
   * information for the data entry display pages.
   *
   * @param $vanid
   * @param $voter
   * @param $mcid
   */
  function reload_voter_status($vanid,$voter,$mcid) {

    $surveyReports = $this->reports->getNlpTypeReports($vanid,'Survey');
    //nlp_debug_msg('$surveyReports',$surveyReports);
    $rIndex = 0;
    foreach ($surveyReports as $surveyReport) {
      if($surveyReport['reportIndex'] > $rIndex) {
        $rIndex = $surveyReport['reportIndex'];
      }
    }
    
    // Update the pledge to vote status for this voter.
    $turfVoter = array();
    $turfVoter['vanid'] = $vanid;
    $turfVoter['pledgedToVote'] = $rIndex;
    $turfVoter['turfIndex'] = $voter['address']['turfIndex'];
    $this->voters->updateTurfVoter($turfVoter);
    
    //  Get the moved report if it exists.
    $request['vanid'] = $vanid;
    $request['type'] = 'contact';
    $request['value'] = 'moved';
    $request['cycle'] = NULL;  // All cycles.
    $movedReport = $this->reports->getReport($request);
    //nlp_debug_msg('$movedReport', $movedReport);
    // If the voter was marked as moved, check if there is a new address.
    if(!empty($movedReport)) {
      $existingAddressObj = json_decode($movedReport['text']);
      $existingAddress =  (array) $existingAddressObj;
      $moveStatus = 1;
      $address = $voter['address'];
      //nlp_debug_msg('address', $address);
      $match = $this->voters->addressCompare($address,$existingAddress);
      if($match) {
        // Voter is at the same address where marked as moved.
        $this->voters->setMovedStatus($voter['address']['turfIndex'] ,$vanid,$moveStatus);
        //$nlsObj->resultsReported($mcid,$county);
      }
    }
    
    //  Get the comment for this election cycle.
    $req['vanid'] = $vanid;
    $req['mcid'] = $mcid;
    //nlp_debug_msg('$req',$req);
    $comment = $this->reports->getComment($req);
    $note = $comment['text'];
    $reportIndex = $comment['reportIndex'];
    $noteId = NULL;
    $turfIndex = $voter['address']['turfIndex'];
    $this->voters->updateTurfNote($turfIndex,$vanid,$note,$reportIndex,$noteId);
    
  }
  
}
