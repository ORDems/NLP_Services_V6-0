<?php /** @noinspection PhpMissingFieldTypeInspection */

namespace Drupal\nlpservices\Form;

use DateTimeZone;
use Drupal;
//use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\TempStore\PrivateTempStoreFactory;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\Core\Datetime\DrupalDateTime;
use Drupal\nlpservices\NlpNls;
use Drupal\nlpservices\NlpVoters;
use Drupal\nlpservices\NlpTurfs;
use Drupal\nlpservices\NlpSurveyQuestion;
use Drupal\nlpservices\NlpSurveyResponses;
use Drupal\nlpservices\NlpPaths;
use Drupal\nlpservices\NlpEncryption;
use Drupal\nlpservices\NlpSessionData;
use Drupal\nlpservices\NlpMatchbacks;
use Drupal\nlpservices\NlpReports;
use Drupal\nlpservices\NlpAwards;
use Drupal\nlpservices\ApiSurveyQuestion;
use Drupal\nlpservices\ApiVoter;
use Drupal\nlpservices\DrupalUser;

/**
 * @noinspection PhpUnused
 */
class DataEntryForm extends FormBase
{
  protected $configFactory;
  protected NlpNls $nls;
  protected NlpVoters $voters;
  protected NlpTurfs $turfs;
  protected NlpSurveyQuestion $surveyQuestion;
  protected NlpSurveyResponses $surveyResponses;
  protected NlpPaths $paths;
  protected NlpEncryption $nlpEncrypt;
  protected NlpSessionData $sessionDataObj;
  protected NlpMatchbacks $matchbacks;
  protected NlpReports $reports;
  protected PrivateTempStoreFactory $privateTempstoreObj;
  protected NlpAwards $awardsObj;
  protected ApiSurveyQuestion $apiSurveyQuestionObj;
  protected ApiVoter $apiVotersObj;
  protected DrupalUser $drupalUserObj;


  public function __construct(  $configFactory ,$nls, $voters, $turfs, $surveyQuestion,
                  $surveyResponses, $paths, $nlpEncrypt, $sessionDataObj , $matchbacks, $reports, $privateTempstoreObj,
                  $awardsObj, $apiSurveyQuestionObj, $apiVotersObj,$drupalUserObj ) {
    $this->configFactory = $configFactory;
    $this->nls = $nls;
    $this->voters = $voters;
    $this->turfs = $turfs;
    $this->surveyQuestion = $surveyQuestion;
    $this->surveyResponses = $surveyResponses;
    $this->paths = $paths;
    $this->nlpEncrypt = $nlpEncrypt;
    $this->sessionDataObj = $sessionDataObj;
    $this->matchbacks = $matchbacks;
    $this->reports = $reports;
    $this->privateTempstoreObj = $privateTempstoreObj;
    $this->awardsObj = $awardsObj;
    $this->apiSurveyQuestionObj = $apiSurveyQuestionObj;
    $this->apiVotersObj = $apiVotersObj;
    $this->drupalUserObj = $drupalUserObj;
  }
  
  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container): DataEntryForm
  {
    return new static(
      $container->get('config.factory'),
      $container->get('nlpservices.nls'),
      $container->get('nlpservices.voters'),
      $container->get('nlpservices.turfs'),
      $container->get('nlpservices.survey_question_nlp'),
      $container->get('nlpservices.survey_responses_nlp'),
      $container->get('nlpservices.paths'),
      $container->get('nlpservices.encryption'),
      $container->get('nlpservices.session_data'),
      $container->get('nlpservices.matchbacks'),
      $container->get('nlpservices.reports'),
      $container->get('tempstore.private'),
      $container->get('nlpservices.awards'),
      $container->get('nlpservices.survey_question'),
      $container->get('nlpservices.api_voter'),
      $container->get('nlpservices.drupal_user'),
    );
  }
  
  /**
   * {@inheritdoc}
   */
  public function getFormId(): string
  {
    return 'nlpservices_data_entry_form';
  }
  
  const DE_PAGE_SIZE = 10;
  
  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state): array
  {
    $tempSessionData = $this->privateTempstoreObj->get('nlpservices.session_data');
    if(!$form_state->get('reenter')) {
      try {
        $tempSessionData->set('currentPage', 0);
      } catch (Drupal\Core\TempStore\TempStoreException $e) {
        nlp_debug_msg('Temp store save error',$e->getMessage());
      }
  
      $canvassDate = date('Y-m-d',time());  // Today.
      try {
        $tempSessionData->set('canvassDate', $canvassDate);
      } catch (Drupal\Core\TempStore\TempStoreException $e) {
        nlp_debug_msg('Temp store save error',$e->getMessage());
      }
      $form_state->set('canvassDate',$canvassDate);
    }
    
    
    if(!$this->initializeDataReporting($form_state)) {
      return $form;
    }
    $county = $form_state->get('county');
    $state = $form_state->get('State');
    $canvassDate = $form_state->get('canvassDate');
    $defaultVoterContactMethod = $form_state->get('$defaultVoterContactMethod');
  
    $mcid = $form_state->get('mcid');
    $turfIndex = $form_state->get('turfIndex');
  
    $form_state->set('defaultValues',NULL);
    
    $turfInfo = $this->fetchVoters($turfIndex,$voters);
    //nlp_debug_msg('$turfInfo',$turfInfo);
    $form_state->set('voterCount',$turfInfo['voterCount']);
    $form_state->set('pageCount',$turfInfo['pageCount']);
    $tempSessionData = $this->privateTempstoreObj->get('nlpservices.session_data');
    $currentPage = $tempSessionData->get('currentPage');
    //nlp_debug_msg('$currentPage',$currentPage);
    //nlp_debug_msg('$voters',$voters);
  
    //$defaultVoterContactMethod = $form_state->get('defaultVoterContactMethod');
    $contactMethods = $form_state->get('contactMethods');
    $preferredContactMethod = array_search($defaultVoterContactMethod, $contactMethods);
  
    $awards = $this->awardsObj->getAward($mcid);
    $form['dateBar'] = $this->createDateBar($canvassDate, $turfInfo, $awards, $currentPage);
    
    $buildInfo['county'] = $county;
    $buildInfo['state'] = $state;
    $buildInfo['currentPage'] = $currentPage;
    $buildInfo['preferredContactMethod'] = $preferredContactMethod;
    $buildInfo['contactMethods'] = $form_state->get('contactMethods');
    $buildInfo['selectedContactMethods'] = $form_state->get('selectedContactMethods');
    $buildInfo['canvassResponseCodes'] = $form_state->get('canvassResponseCodes');
    //$buildInfo['defaultValues'] = $form_state->get('defaultValues');
    $buildInfo['defaultValues'] = NULL;
    //nlp_debug_msg('$buildInfo',$buildInfo);

    $form['voters'] = $this->buildVoterTable($voters, $buildInfo);
    //nlp_debug_msg('$buildInfo',$buildInfo);
    $form_state->set('displayedVanids',$buildInfo['vanids']);
    $form_state->set('defaultValues',$buildInfo['defaultValues']);
    $form_state->set('optionsDisplay',$buildInfo['optionsDisplay']);

    $form['navigate'] = $this->navigate($turfInfo['voterCount'],$turfInfo['pageCount'],$currentPage);
    
    return $form;
  }
  
  /**
   * {@inheritdoc}
   */
  public function validateForm(array &$form, FormStateInterface $form_state)
  {
    $values = $form_state->getValues();
    //nlp_debug_msg('$values',$values);
    //nlp_debug_msg('validate',time());
    
    foreach ($values as $valueId=>$value) {
      $valueIdParts = explode('-',$valueId);
      if ($valueIdParts[0] == 'contact_method') {
        $vanid = $valueIdParts[1];
        $selectedContactMethods = $form_state->get('selectedContactMethods');
        $selectedContactMethods[$vanid] = $value;
        $form_state->set('selectedContactMethods',$selectedContactMethods);
      }
    }
  }
  
  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state)
  {
    $form_state->setRebuild();
    $form_state->set('reenter',TRUE);
    $messenger = Drupal::messenger();
    $values = $form_state->getValues();
    //nlp_debug_msg('$values',$values);
  
    $newCanvassDate = $values['canvassDate'];
    $canvassDate = $form_state->get('canvassDate');
    if($canvassDate != $newCanvassDate) {
      $form_state->set('canvassDate',$newCanvassDate);
      $tempSessionData = $this->privateTempstoreObj->get('nlpservices.session_data');
      try {
        $tempSessionData->set('canvassDate', $newCanvassDate);
      } catch (Drupal\Core\TempStore\TempStoreException $e) {
        nlp_debug_msg('Temp store save error',$e->getMessage());
      }
      $canvassDate = $newCanvassDate;
    }
    $form_state->set('canvassDate',$canvassDate);

    $defaultValues = $form_state->get('defaultValues');
    //nlp_debug_msg('$defaultValues',$defaultValues);
    $nlsInfo = $form_state->get('nlsInfo');
    if(empty($nlsInfo)) {
      $mcid = $form_state->get('mcid');
      $nlsInfo = $this->nls->getNlById($mcid);
    }
  
    $stateCommitteeKey = $form_state->get('stateCommitteeKey');
    
    $contactMethods = $form_state->get('contactMethods');
    $canvassResponseCodes = $form_state->get('canvassResponseCodes');
  
    $turfIndex = $form_state->get('turfIndex');
    
    $actions = [];
    foreach ($values as $valueId=>$value) {
      $valueIdParts = explode('-',$valueId);
      $reportType = $valueIdParts[0];
      //nlp_debug_msg('$valueId',$valueId);
      switch ($reportType) {
        case 'contact_method':
          if(empty($value)) {break;}
          $vanid = $valueIdParts[1];
          $contactMethod = $contactMethods[$value];
          $actions[$vanid]['vanid'] = $vanid;
          $actions[$vanid][$reportType] = $contactMethod;
          $actions[$vanid]['cid'] = $canvassResponseCodes[$contactMethod]['code'];
          break;
        case 'no_contact':
          if(empty($value)) {break;}
          nlp_debug_msg('$valueId',$valueId);
          $voterIndex = $valueIdParts[1];
          $vanids = $form_state->get('displayedVanids');
          $vanid = $vanids[$voterIndex];
          
          if(empty($actions[$vanid]['contact_method'])) {break;}
          //nlp_debug_msg('contact_method',$actions[$vanid]['contact_method']);
          $optionsDisplay = $form_state->get('optionsDisplay');
          $chosenOption = $optionsDisplay[$vanid][$value];
          //nlp_debug_msg('chosenOption',$chosenOption);
          $actions[$vanid][$reportType]['chosenOption'] = $chosenOption;
  
          $contactMethod = $actions[$vanid]['contact_method'];
          //nlp_debug_msg('$canvassResponseCodes',$canvassResponseCodes);
          $actions[$vanid][$reportType]['rid'] = $canvassResponseCodes[$contactMethod]['responses'][$chosenOption];
  
          $form_state->setValue($valueId,0);
          $form_state->setUserInput([$valueId=>0,]);
          break;
        case 'postcardMailed':
          if(empty($value)) {break;}  // Not set.
          $vanid = $valueIdParts[1];
          //nlp_debug_msg('$vanid',$vanid);
          $postcardSend = $this->reports->voterSentPostcard($vanid);
          if($postcardSend) {break;}  // Card already reported as sent.
          $actions[$vanid]['vanid'] = $vanid;
          $actions[$vanid]['contact_method'] = 'Postcard';
          $actions[$vanid]['no_contact']['chosenOption'] = 'Mailed';
          $actions[$vanid]['no_contact']['rid'] = $canvassResponseCodes['Postcard']['responses']['Mailed'];
          $actions[$vanid]['cid'] = $canvassResponseCodes['Postcard']['code'];
          //nlp_debug_msg('$actions',$actions);
          
          break;
        case 'pledge2vote':
          if(empty($value)) {break;}
  
          $vanid = $valueIdParts[1];
          $actions[$vanid][$reportType] = $value;
          //nlp_debug_msg('$valueId',$valueId);
          $form_state->setValue($valueId,0);
          $form_state->setUserInput([$valueId=>0,]);
          break;
        case 'moved':
        case 'hostile':
        case 'deceased':
          $vanid = $valueIdParts[1];
          $defaultValue = !empty($defaultValues[$vanid][$reportType]);
          //nlp_debug_msg('$defaultValue'.' '.$vanid,$defaultValue);
          $currentValue = !empty($value);
          //nlp_debug_msg('$currentValue'.' '.$vanid,$currentValue);
          if($defaultValue != $currentValue) {
            //nlp_debug_msg('$value'.' '.$vanid,$value);
            if($reportType == 'deceased' OR $reportType == 'moved') {
              if(!empty($defaultValue)) {break;}  // Deceased and moved can't be undone this way.
            }
            // Use "Walk" as contact method if the NL did not set a method.
            if(empty($actions[$vanid]['contact_method'])) {
              $contactMethod = 'Walk';
              $actions[$vanid]['vanid'] = $vanid;
              $actions[$vanid]['contact_method'] = $contactMethod;
            }
            $contactMethod = $actions[$vanid]['contact_method'];
            $actions[$vanid]['cid'] = $canvassResponseCodes[$contactMethod]['code'];
            $actions[$vanid][$reportType]['name'] = 'Moved';
            $actions[$vanid][$reportType]['value'] = $value;
            if($reportType == 'hostile') {
              $actions[$vanid][$reportType]['rid'] = $form_state->get('nlpHostile');
            } elseif ($reportType == 'deceased') {
              //nlp_debug_msg('$canvassResponseCodes',$canvassResponseCodes);
              $actions[$vanid][$reportType]['rid'] = $canvassResponseCodes[$contactMethod]['responses']['Deceased'];
            } else {
              if(empty($canvassResponseCodes[$contactMethod]['responses']['Moved'])) {
                $nice = highlight_string("<?php\n\$canvassResponseCodes 493 =\n" . var_export($actions, true) . ";\n?>", TRUE);
                Drupal::logger('nlpservices')->notice($nice);
                Drupal::logger('nlpservices')->notice($contactMethod);
                $rid = 0;
              } else {
                $rid = $canvassResponseCodes[$contactMethod]['responses']['Moved'];
              }
              $actions[$vanid][$reportType]['rid'] = $rid;
            }
            
          }
          //nlp_debug_msg('$actions',$actions);
          break;
        case 'note':
          if(empty($value)) {break;}
          $vanid = $valueIdParts[1];
          if(empty($actions[$vanid]['contact_method'])) {
            $contactMethod = 'Walk';
            $actions[$vanid]['vanid'] = $vanid;
            $actions[$vanid]['contact_method'] = $contactMethod;
            $actions[$vanid]['cid'] = $canvassResponseCodes[$contactMethod]['code'];
          }
          //nlp_debug_msg('$actions',$actions);

          // Report a new note or a changed note.
          if(empty($defaultValues[$vanid]['note']) OR $defaultValues[$vanid]['note'] != $value) {
            $actions[$vanid][$reportType]['text'] = $value;
            if(!empty($defaultValues[$vanid]['reportIndex'])) {
              $actions[$vanid][$reportType]['reportIndex'] = $defaultValues[$vanid]['reportIndex'];
            }
          }
          //nlp_debug_msg('$actions',$actions);

          $form_state->setValue($valueId,0);
          $form_state->setUserInput([$valueId=>NULL,]);
          break;
        case 'contact_update':
          if(empty($value)) {break;}
          //nlp_debug_msg('$actions',$actions);
          $vanid = $valueIdParts[1];
          $field = $valueIdParts[2];

          $voter = $this->voters->getVoterById($vanid,$turfIndex);
          $voterName = $voter['lastName'].', '.$voter['firstName'];

          switch ($field)
          {
            case 'type':  // A change in contact info.
              switch ($value)
              {
                case 'NE':  // New email.
                  $newContactParts = $valueIdParts;
                  $newContactParts[2] = 'value';
                  $newContact = implode('-',$newContactParts);
                  nlp_debug_msg('$newContact',$newContact);
                  if(empty($values[$newContact]))  {
                    $messenger->addWarning('You must enter a new email, Voter is '.$voterName);
                    break;
                  }
                  $actions[$vanid][$reportType][$field] = $value;
                  break;
                case 'NC':  // New cell.
                  $newContactParts = $valueIdParts;
                  $newContactParts[2] = 'value';
                  $newContact = implode('-',$newContactParts);
                  if(empty($values[$newContact]))  {
                    $messenger->addWarning('You must enter a new cell number, Voter is '.$voterName);
                    break;
                  }
                  $actions[$vanid][$reportType][$field] = $value;
                  break;
                case 'NH':  // New home phone.
                  $newContactParts = $valueIdParts;
                  $newContactParts[2] = 'value';
                  $newContact = implode('-',$newContactParts);
                  if(empty($values[$newContact]))  {
                    $messenger->addWarning('You must enter a new phone number, Voter is '.$voterName);
                    break;
                  }
                  $actions[$vanid][$reportType][$field] = $value;
                  break;
                case 'BC':  // Bad cell.
                  $voter = $this->voters->getVoterById($vanid,$turfIndex);
                  $actions[$vanid][$reportType]['homePhoneId'] = NULL;
                  if(!empty($voter['homePhoneId'])) {
                    $actions[$vanid][$reportType]['homePhoneId'] = $voter['homePhoneId'];
                  }
                  $wrongNumber = $canvassResponseCodes['Phone']['responses']['Wrong Number'];
                  $actions[$vanid][$reportType]['wrongNumberCode'] = $wrongNumber;
                  $actions[$vanid][$reportType]['homePhone'] = $voter['homePhone'];
                  $actions[$vanid][$reportType][$field] = $value;
                  break;
                case 'BH':  // Bad home phone.
                  $voter = $this->voters->getVoterById($vanid,$turfIndex);
                  $actions[$vanid][$reportType]['cellPhoneId'] = NULL;
                  if(empty($voter['cellPhoneId'])) {
                    $actions[$vanid][$reportType]['cellPhoneId'] = $voter['cellPhoneId'];
                  }
                  $wrongNumber = $canvassResponseCodes['Phone']['responses']['Wrong Number'];
                  $actions[$vanid][$reportType]['wrongNumberCode'] = $wrongNumber;
                  $actions[$vanid][$reportType]['cellPhone'] = $voter['cellPhone'];
                  $actions[$vanid][$reportType][$field] = $value;
                  break;
              }
              break;
            case 'value':    // New phone # or email, but may be a comment.

              if(empty($actions[$vanid][$reportType]['type'])) {
                $messenger->addWarning('A comment is not permitted in the Update Contact Info text box, Voter is '.$voterName);
                break;
              }
              switch ($actions[$vanid][$reportType]['type']) {
                case 'BC':
                case 'BH':
                  $messenger->addWarning('A comment for bad phone numbers is not permitted, Voter is '.$voterName);
                  break;
                case 'NE':
                  if (!filter_var($value, FILTER_VALIDATE_EMAIL)) {
                    $messenger->addWarning('Invalid email format, Voter is '.$voterName);
                    break;
                  }
                  $actions[$vanid][$reportType]['new_email'] = $value; // New email.
                  break;
                case 'NC':
                case 'NH':
                  // Allow +, - and . in phone number
                  $filteredPhoneNumber = filter_var($value, FILTER_SANITIZE_NUMBER_INT);
                  $phoneToCheck = str_replace("-", "", $filteredPhoneNumber);
                  if (strlen($phoneToCheck) < 10 || strlen($phoneToCheck) > 14) {
                    $messenger->addWarning('Invalid phone format, Voter is '.$voterName);
                    break;
                  }
                  $actions[$vanid][$reportType]['new_phone_number'] = $value; // New cell/phone.
                  break;
                }

              break;
          }

          //nlp_debug_msg('$actions',$actions);
          $form_state->setValue($valueId,0);
          $form_state->setUserInput([$valueId=>0,]);
          break;
      }
    }
    //nlp_debug_msg('$actions',$actions);
  
    // Remove any result without an action.
    foreach ($actions as $vanid=>$action) {
      if(count($action) <= 3) {
        unset($actions[$vanid]);
      }
    }

    if(!empty($actions)) {
      // Something was reported that needs to be recorded.
      $actions[0]['mcid'] = $form_state->get('mcid');
      $actions[0]['county'] = $form_state->get('county');
      $actions[0]['turfIndex'] = $form_state->get('turfIndex');
      $actions[0]['contactDate'] = $canvassDate;
      $actions[0]['cycle'] = $form_state->get('cycle');
      $actions[0]['firstName'] = $nlsInfo['firstName'];
      $actions[0]['lastName'] = $nlsInfo['lastName'];
      $actions[0]['stateCommitteeKey'] = $stateCommitteeKey;
      //nlp_debug_msg('$actions',$actions);
      $this->recordActions($actions);
    }
    
    $triggeringElement = $form_state->getTriggeringElement();
    $elementClicked = $triggeringElement['#name'];
    //nlp_debug_msg('$elementClicked '.time(),$elementClicked);
    $tempSessionData = $this->privateTempstoreObj->get('nlpservices.session_data');

    $navButtonParts = explode('-',$elementClicked);
    
    switch ($navButtonParts[0]) {
      case 'save_reports':
        /**
        $pageCount = $form_state->get('pageCount');
        $currentPage = $tempSessionData->get('currentPage');
        $currentPage++;
        $page = ($currentPage<$pageCount)?$currentPage:0;
        try {
          $tempSessionData->set('currentPage', $page);
        } catch (Drupal\Core\TempStore\TempStoreException $e) {
          nlp_debug_msg('Temp store save error',$e->getMessage());
        }
         */
        break;
      case 'pageSelect':
        $page = $navButtonParts[1]-1;
        try {
          $tempSessionData->set('currentPage', $page);
        } catch (Drupal\Core\TempStore\TempStoreException $e) {
          nlp_debug_msg('Temp store save error',$e->getMessage());
        }
        break;
      case 'next':
        try {
          $tempSessionData->set('currentPage', 6);
        } catch (Drupal\Core\TempStore\TempStoreException $e) {
          nlp_debug_msg('Temp store save error',$e->getMessage());
        }
        break;
      case 'previous':
        try {
          $tempSessionData->set('currentPage', 5);
        } catch (Drupal\Core\TempStore\TempStoreException $e) {
          nlp_debug_msg('Temp store save error',$e->getMessage());
        }
        
        break;
      case 'last_name_search':
        $turfIndex = $form_state->get('turfIndex');
        $lastName = $values['last-name'];
        $searchResult = $this->voterSearch($turfIndex,$lastName);
        if($searchResult['found']) {
          try {
            $tempSessionData->set('currentPage', $searchResult['page']);
          } catch (Drupal\Core\TempStore\TempStoreException $e) {
            nlp_debug_msg('Temp store save error',$e->getMessage());
          }
        }
        break;
    }
    
  }
  
  /** * * * * * * * * * * * * * * * * * * * * * * * * * * * * * *
   * recordActions
   * 
   * @param $actions
   */
  function recordActions($actions)
  {
    $messenger = Drupal::messenger();
    $common = $actions[0];
    //$contactType = $common['contact_method'];
    //nlp_debug_msg('$actions',$actions);
    foreach ($actions as $vanid=>$action) {
      if(empty($vanid)) {continue;}
      foreach ($action as $actionId=>$value) {
        switch ($actionId) {

          /*
         * Survey question ----------------------------------------------------
         */
          case 'pledge2vote':
            // Save the survey question response in NLP Services.
            $contactType = $action['contact_method'];
            $turfIndex = $common['turfIndex'];
            $voter = $this->voters->getVoterById($vanid, $turfIndex);
            //nlp_debug_msg('$voter',$voter);
            if($contactType == 'postcard') {
              $voterName = $voter['firstName'].' '.$voter['lastName'];
              $messenger->addWarning('If you received an answer from '.$voterName
                .' please change the contact method to something other than postcard.');
              break;
            }

            $rid = $value;
            //$contactType = $action['contact_method'];
            $county = $common['county'];
            $mcid = $common['mcid'];
            //$cid = $this->responseCodesObj->getCid($contactType);

            $config = $this->config('nlpservices.configuration');
            //$apiKeys = $config->get('nlpservices-api-keys');
            //$stateCommitteeKey = $apiKeys['State Committee'];
            $stateCommitteeKey = $common['stateCommitteeKey'];
            $currentResponseCodes = $config->get('nlpservices_canvass_response_codes');
            //nlp_debug_msg('$currentResponseCodes',$currentResponseCodes);
            $cid = $currentResponseCodes[$contactType]['code'];

            //$questionsArray = $this->surveyQuestion->getSurveyQuestions('pledge',$stateCommitteeKey);
            //$questionArray = reset($questionsArray);

            $surveyQuestions = $config->get('nlpservices_survey_questions');
            $pledgeQuestion = $surveyQuestions['state'];
            //nlp_debug_msg('$pledgeQuestion',$pledgeQuestion);
            $qid = $pledgeQuestion['surveyQuestionId'];
            //$surveyResponseList = $this->surveyResponses->getSurveyResponseList($qid);
            $surveyResponseList = $pledgeQuestion['responses'];
            //nlp_debug_msg('$rid',$rid);
            $result['value'] = str_replace(':fn',$voter['firstName'],$surveyResponseList[$rid]['name']);
            $result['text'] = $pledgeQuestion['name'];
            $result['qid'] = $qid;

            $result['vanid'] = $vanid;
            $result['mcid'] = $mcid;
            $result['county'] = $county;
            $result['cycle'] = $common['cycle'];
            $result['contactDate'] = $common['contactDate'];
            $result['active'] = TRUE;
            $result['firstName'] = $common['firstName'];
            $result['lastName'] = $common['lastName'];
            $result['contactType'] = $contactType;
            $result['type'] = 'Survey';
            $result['rid'] = $rid;
            $result['cid'] = $cid;
            //nlp_debug_msg('$result',$result);
            $rIndex = $this->reports->setNlReport($result);
            // Report the response to VoteBuilder.
            $surveyResponse['type'] = 'Survey';
            $surveyResponse['vanid'] = $vanid;
            $surveyResponse['qid'] = $qid;
            $surveyResponse['rid'] = $rid;
            $surveyResponse['cid'] = $cid;
            //$dateTimeObj = new DateTime($date);
            //$canvassDate = $dateTimeObj->format(DateTime::ATOM);

            $canvassDate = date(DATE_ATOM, strtotime($common['contactDate']));


            $surveyResponse['dateCanvassed'] = $canvassDate;
            $surveyResponse['ContactTypeCode'] = $cid;
            //nlp_debug_msg('$surveyResponse',$surveyResponse);
            //nlp_debug_msg('$apiSurveyQuestionObj',$apiSurveyQuestionObj);
            $this->apiSurveyQuestionObj->setApiSurveyResponse($stateCommitteeKey, $surveyResponse);
            //nlp_debug_msg('$apiSurveyQuestionObj',$apiSurveyQuestionObj);
            // Mark this voter pledge response.
            $turfVoter = array();
            $turfVoter['vanid'] = $vanid;
            $turfVoter['mcid'] = $mcid;
            $turfVoter['county'] = $county;
            $turfVoter['turfIndex'] = $common['turfIndex'];
            $turfVoter['pledgedToVote'] = $rIndex;
            //nlp_debug_msg('$turfVoter',$turfVoter);
            $this->voters->updateTurfVoter($turfVoter);

            // This NL has reported results.
            $this->nls->resultsReported($mcid,$county);
            break;
          /*
          * No contact ---------------------------------------------------------
          */
          case 'no_contact':
            $contactType = $action['contact_method'];
            $resp['contactType'] = $contactType;
            $resp['contactDate'] = $common['contactDate'];
            $resp['cycle'] = $common['cycle'];
            $resp['cid'] =  $action['cid'];
            $resp['rid'] = $action['no_contact']['rid'];
            $resp['value'] = $action['no_contact']['chosenOption'];
            $resp['vanid'] = $vanid;
            $resp['mcid'] = $common['mcid'];
            $resp['county'] = $common['county'];
            $resp['turfIndex'] = $common['turfIndex'];
            $resp['stateCommitteeKey'] = $common['stateCommitteeKey'];
            //nlp_debug_msg('resp',$resp);
            $this->noContactResponse($resp);
            break;
            
          /*
          * Hostile -----------------------------------------------------------
          */
          case 'hostile':
            $hostileValue = (!empty($value['value']))?1:0;
            $resp['value'] = $hostileValue;
            $contactType = $action['contact_method'];
            $rid = $value['rid'];
            $cid = $action['cid'];
            $resp['contactType'] = $contactType;
            $resp['contactDate'] = $common['contactDate'];
            $resp['qid'] = NULL;
            $resp['cid'] = $cid;
            $resp['rid'] = $rid;
            $resp['vanid'] = $vanid;
            $resp['mcid'] = $common['mcid'];
            $resp['county'] = $common['county'];
            $resp['turfIndex'] = $common['turfIndex'];
            $resp['stateCommitteeKey'] = $common['stateCommitteeKey'];
            $resp['cycle'] = $common['cycle'];
            //nlp_debug_msg('resp',$resp);
            $this->hostileVoter($resp);
    
            // Remember the state of the NLP Hostile.
            $voterStatus = $this->voters->getVoterStatus($vanid);
            $voterStatus['hostile'] = $hostileValue;
            $this->voters->setVoterStatus($vanid, $voterStatus);
            break;

          /*
          * Hostile -----------------------------------------------------------
          */
          case 'deceased':  // Deceased.
            $contactType = $action['contact_method'];
            $resp['contactType'] = $contactType;
            $resp['contactDate'] = $common['contactDate'];
            $resp['cid'] = $action['cid'];
            $resp['rid'] = $value['rid'];
            $resp['stateCommitteeKey'] = $common['stateCommitteeKey'];
            $resp['cycle'] = $common['cycle'];
            $resp['vanid'] = $vanid;
            $resp['mcid'] = $common['mcid'];
            $resp['county'] = $common['county'];
            $resp['turfIndex'] = $common['turfIndex'];
            //nlp_debug_msg('resp',$resp);
            $this->deceasedVoter($resp);
            
            // Remember the state of the NLP Deceased activist code in VoteBuilder.
            $voterStatus = $this->voters->getVoterStatus($vanid);
            $voterStatus['deceased'] = 1;
            $this->voters->setVoterStatus($vanid, $voterStatus);
            break;
  
          /*
          * Moved ------------------------------------------------------------
          */
          case 'moved':
            $movedStatus = (!empty($value))?1:0;
            $turfIndex = $common['turfIndex'];
            $addressInNlp = $this->voters->fetchVoterAddress($vanid,$turfIndex);
            //('$addressInNlp',$addressInNlp);
            $committeeKey = $common['stateCommitteeKey'];
            $voterInVoteBuilder = $this->apiVotersObj->getApiVoter($committeeKey,0,$vanid);
            $addressInVoteBuilder = $voterInVoteBuilder['address'];
            //nlp_debug_msg('$voterInVoteBuilder',$voterInVoteBuilder);
  
            $sameAddress = $this->voters->addressCompare($addressInNlp,$addressInVoteBuilder);
            if($movedStatus) {
              $canvassDate = date(DATE_ATOM, strtotime($common['contactDate']));
              $surveyResponse['dateCanvassed'] = $canvassDate;
              if($sameAddress) {
                //  Report the canvass response code to VoteBuilder.
                $surveyResponse['vanid'] = $vanid;
                $surveyResponse['type'] = 'contact';
                $surveyResponse['rid'] = $value['rid'];
                $surveyResponse['ContactTypeCode'] = $action['cid'];
                //nlp_debug_msg('$surveyResponse',$surveyResponse);
                $this->apiSurveyQuestionObj->setApiSurveyResponse($common['stateCommitteeKey'],$surveyResponse);
              }
              //nlp_debug_msg('$action',$action);
              $simpleAddress = $this->voters->addressExtract($addressInVoteBuilder);
              $addressEncode = json_encode($simpleAddress);
              $result['reportIndex'] = NULL;
              $result['active'] = TRUE;
              $result['vanid'] = $vanid;
              $result['mcid'] = $common['mcid'];
              $result['county'] = $common['county'];
              $result['contactType'] = $action['contact_method'];
              $result['type'] = 'contact';
              $result['value'] = $action['moved']['name'];
              $result['text'] = $addressEncode;
              $result['cycle'] = $common['cycle'];
              $result['contactDate'] = $common['contactDate'];
              $result['rid'] = $action['moved']['rid'];
              $result['cid'] = $action['cid'];
              //nlp_debug_msg('$result',$result);
              $this->reports->mergeReport($result);
  
              //$turfIndex = $common['turfIndex'];
              $this->voters->setMovedStatus($turfIndex,$vanid,$movedStatus);
              // Results reported by this NL.
              $this->nls->resultsReported($common['mcid'],$common['county']);
              
            } else {
              if($sameAddress) {
                $request['vanid'] = $vanid;
                $request['type'] = 'contact';
                $request['value'] = $action['moved']['name'];
                $request['cycle'] = $common['cycle'];
                //nlp_debug_msg('$request',$request);
                $report = $this->reports->getReport($request);
                $existingRIndex = (!empty($report['reportIndex']))?
                  $report['reportIndex']:NULL;  // Report exists.
                
                if(!empty($existingRIndex)) {
                  $this->reports->deletereport($existingRIndex);
                }
              }
              $this->voters->setMovedStatus($turfIndex,$vanid,$movedStatus);
            }
            break;
  
          /*
          * Note ---------------------------------------------------------------
          */
          case 'note':
            $noteString = trim(strip_tags(htmlentities(stripslashes($value['text']),ENT_QUOTES)));
            $commentMax = $this->reports::MAX_COMMENT;
            if (strlen($noteString) > $commentMax) {
              $note = substr($noteString,0,$commentMax);  // Truncate the comment.
            } else {
              $note = $noteString;
            }
            $note = str_replace("\r\n", "<br>", $note);
    
            // if a note for this voter already exists, update that note else create one.
            $result['vanid'] = $action['vanid'];
            $result['mcid'] = $common['mcid'];
            $result['county'] = $common['county'];
            $result['cycle'] = $common['cycle'];
            $result['contactDate'] = $common['contactDate'];
    
            if(empty($value['reportIndex'])) {
              $result['type'] = 'Comment';
              $result['value'] = '';
              $result['text'] = $note;
              $result['noteId'] = (empty($value['noteId']))?NULL:$value['noteId'];
              //$result['active'] = TRUE;
              $result['contactType'] = $action['contact_method'];
              $result['qid'] = NULL;
              $result['cid'] = NULL;
              $result['rid'] = NULL;
              $responseIndex = $this->reports->setNlReport($result);
            } else {
              $result['reportIndex'] = $responseIndex = $value['reportIndex'];
              $result['text'] = $note;
              $this->reports->updateComment($result);
            }
            //nlp_debug_msg('$result',$result);
  
            $noteId = (empty($value['noteId']))?NULL:$value['noteId'];
            $turfIndex = $common['turfIndex'];
            $this->voters->updateTurfNote($turfIndex,$vanid,$note,$responseIndex,$noteId);
            break;
  
          /*
          * Contact update  ---------------------------------------------------
          */
          case 'contact_update':

            $result['contactType'] = $action['contact_method'];
            $result['vanid'] = $vanid;
            $result['mcid'] = $common['mcid'];
            $result['county'] = $common['county'];
            $result['cycle'] = $common['cycle'];
            $result['contactDate'] = $common['contactDate'];
            $result['noteId'] = NULL;
            $result['active'] = TRUE;
            $result['qid'] = $result['rid'] = $result['cid'] = NULL;
            $result['type'] = 'contact';

            $surveyResponse = [];
            $surveyResponse['vanid'] = $vanid;
            $surveyResponse['type'] = 'contact';
            $surveyResponse['dateCanvassed'] = $common['contactDate'];

            switch ($value['type']) {
              case 'BC':
                $result['contactType'] = 'phone';
                $result['text'] = $value['cellPhone'];
                $result['value'] = 'Wrong number: '.$value['cellPhone'];
                $result['rid'] = $value['wrongNumberCode'];
                $result['cid'] = $value['cid'];
                //nlp_debug_msg('result',$result);
                $this->reports->setNlReport($result);

                $newPhoneNumber = [
                  'cellPhone' => NULL,
                  'cellPhoneId' => NULL
                ];
                $this->voters->updateVoterPhone($vanid,$newPhoneNumber);

                $surveyResponse['rid'] = $value['wrongNumberCode'];
                $surveyResponse['ContactTypeCode'] = $value['cid'];
                $surveyResponse['phoneId'] = $value['cellPhoneId'];
                //nlp_debug_msg('surveyResponse', $surveyResponse);
                if(!empty($value['cellPhoneId'])) {
                  $this->apiSurveyQuestionObj->setApiSurveyResponse($common['stateCommitteeKey'],$surveyResponse);
                }
                break;
              case 'BH':
                $result['contactType'] = 'phone';
                $result['text'] = $value['homePhone'];
                $result['value'] = 'Wrong number: '.$value['homePhone'];
                $result['rid'] = $value['wrongNumberCode'];
                $result['cid'] = $value['cid'];
                //nlp_debug_msg('result',$result);
                $this->reports->setNlReport($result);

                $newPhoneNumber = [
                  'homePhone' => NULL,
                  'homePhoneId' => NULL
                ];
                $this->voters->updateVoterPhone($vanid,$newPhoneNumber);
                $surveyResponse['rid'] = $value['wrongNumberCode'];
                $surveyResponse['ContactTypeCode'] = $value['cid'];
                $surveyResponse['phoneId'] = $action['contact_update']['homePhoneId'];
                //nlp_debug_msg('surveyResponse', $surveyResponse);
                if(!empty($value['homePhoneId'])) {
                  $this->apiSurveyQuestionObj->setApiSurveyResponse($common['stateCommitteeKey'],$surveyResponse);
                }
                break;
              case 'NC':
                $result['value'] = 'New cell number';
                $result['text'] = $value['new_phone_number'];
                //nlp_debug_msg('result',$result);
                $this->reports->setNlReport($result);
                $newPhoneNumber = [
                  'cellPhone' => $value['new_phone_number'],
                  'cellPhoneId' => NULL
                ];
                $this->voters->updateVoterPhone($vanid,$newPhoneNumber);
                break;
              case 'NH':
                $result['value'] = 'New home number';
                $result['text'] = $value['new_phone_number'];
                //nlp_debug_msg('result',$result);
                $this->reports->setNlReport($result);
                $newPhoneNumber = [
                  'homePhone' => $value['new_phone_number'],
                  'homePhoneId' => NULL
                ];
                $this->voters->updateVoterPhone($vanid,$newPhoneNumber);
                break;
              case 'NE':  // New email for voter.$result['type'] = 'New number';
                $result['value'] = 'New email';
                $result['text'] = $value['new_email'];
                //nlp_debug_msg('result',$result);
                $this->reports->setNlReport($result);
                break;
            }
        }
      }
    }
  }
  
  /** * * * * * * * * * * * * * * * * * * * * * * * * * * * * * *
   * noContactResponse
   *
   * @param $resp
   * @noinspection DuplicatedCode
   */
  function noContactResponse($resp) {
    $stateCommitteeKey = $resp['stateCommitteeKey'];
    // Save the contact attempt in NLP Services.
    $result['county'] = $resp['county'];
    $result['vanid'] = $resp['vanid'];
    $result['mcid'] = $resp['mcid'];
    $result['cycle'] = $resp['cycle'];
    $result['contactDate'] = $resp['contactDate'];
    $result['active'] = TRUE;
    $result['contactType'] = $resp['contactType'];
    $result['type'] = 'contact';
    $result['text'] = '';
    $result['value'] = $resp['value'];
    $result['qid'] = NULL;
    $result['rid'] = $resp['rid'];
    $result['cid'] = $resp['cid'];
    //nlp_debug_msg('$result',$result);
    $rIndex = $this->reports->setNlReport($result);
    
    //  Report the canvass response code to VoteBuilder.
    if(!empty($resp['rid'])){
      $surveyResponse['vanid'] = $resp['vanid'];
      $surveyResponse['type'] = 'contact';
      $surveyResponse['rid'] = $resp['rid'];
      
      $canvassDate = date(DATE_ATOM, strtotime($resp['contactDate']));
  
      $surveyResponse['dateCanvassed'] = $canvassDate;
      $surveyResponse['ContactTypeCode'] = $resp['cid'];
      //nlp_debug_msg('$surveyResponse',$surveyResponse);
      $this->apiSurveyQuestionObj->setApiSurveyResponse($stateCommitteeKey,$surveyResponse);
      //nlp_debug_msg('$apiSurveyQuestionObj',$apiSurveyQuestionObj);
    }
    
    // Mark Contact attempt for this voter.
    $turfVoter = array();
    $turfVoter['vanid'] = $resp['vanid'];
    $turfVoter['mcid'] = $resp['mcid'];
    $turfVoter['county'] = $resp['county'];
    $turfVoter['turfIndex'] = $resp['turfIndex'];
    $turfVoter['attemptedContact'] = $rIndex;
    $turfVoter['reportIndex'] = $rIndex;
  
    //nlp_debug_msg('$turfVoter',$turfVoter);
    $this->voters->updateTurfVoter($turfVoter);
    
    // This NL has reported results.
    $this->nls->resultsReported($resp['mcid'],$resp['county']);
  }
  
  /** * * * * * * * * * * * * * * * * * * * * * * * * * * * * * *
   * hostileVoter
   *
   * @param $resp
   * @noinspection DuplicatedCode
   */
  function hostileVoter($resp) {
    // Record the change in the Hostile status for this voter in the NLP database.
    $respValues = array('county','vanid','mcid','contactType','rid','cid','qid','contactDate','cycle','value');
    foreach($respValues as $respValue) {
      $result[$respValue] = $resp[$respValue];
    }
    $result['active'] = TRUE;
    $result['reportIndex'] = $this->reports->getAcReportIndex($resp['vanid'],
      $resp['cycle'],'NLPHostile');
    $result['type'] = 'Activist';
    $result['text'] = 'NLPHostile';
    //nlp_debug_msg('$result',$result);
    $this->reports->setNlAcReport($result);
    
    // Record the change of the NLP Hostile activist code.
    $surveyResponse['type'] = 'Activist';
    $surveyResponse['contactType'] = $resp['contactType'];
    $surveyResponse['dateCanvassed'] = NULL;
    $surveyResponse['vanid'] = $resp['vanid'];
    $surveyResponse['action'] = $resp['value'];
    $surveyResponse['rid'] = $resp['rid'];
    $surveyResponse['ContactTypeCode'] = $resp['cid'];
    //nlp_debug_msg('$surveyResponse',$surveyResponse);
    //nlp_debug_msg('$apiSurveyQuestionObj',$apiSurveyQuestionObj);
    $this->apiSurveyQuestionObj->setApiSurveyResponse($resp['stateCommitteeKey'],$surveyResponse);
    //nlp_debug_msg('$apiSurveyQuestionObj',$apiSurveyQuestionObj);
    // Results reported by this NL.
    $this->nls->resultsReported($resp['mcid'],$resp['county']);
  }
  
  /** * * * * * * * * * * * * * * * * * * * * * * * * * * * * * *
   * deceasedVoter
   *
   * @param $resp
   * @noinspection DuplicatedCode
   */
  function deceasedVoter($resp) {
    // This voter is being reported as deceased.  Record in NLP Services.
    $respValues = array('county','vanid','mcid','contactType','rid','cid', 'contactDate','cycle','value');
    foreach($respValues as $respValue) {
      $result[$respValue] = $resp[$respValue];
    }
    $result['reportIndex'] = NULL;
    $result['active'] = TRUE;
    $result['type'] = 'contact';
    $result['text'] = '';
    $result['qid'] = NULL;
    //nlp_debug_msg('$result',$result);
    $rIndex = $this->reports->mergeReport($result);
    
    // Report the canvass response code for deceased to VoteBuilder.
    $surveyResponse['vanid'] = $resp['vanid'];
    $surveyResponse['type'] = 'contact';
    $surveyResponse['rid'] = $resp['rid'];
    $canvassDate = date(DATE_ATOM, strtotime($resp['contactDate']));
    //nlp_debug_msg('$canvassDate',$canvassDate);
    //$canvassDate = $dateTimeObj->format(DateTime::ATOM);
    $surveyResponse['dateCanvassed'] = $canvassDate;
    $surveyResponse['ContactTypeCode'] = $resp['cid'];
    //nlp_debug_msg('$surveyResponse',$surveyResponse);
    $this->apiSurveyQuestionObj->setApiSurveyResponse($resp['stateCommitteeKey'],$surveyResponse);
    
    // Mark Contact attempt for this voter.
    $turfVoter = array();
    $turfVoter['vanid'] = $resp['vanid'];
    $turfVoter['mcid'] = $resp['mcid'];
    $turfVoter['county'] = $resp['county'];
    $turfVoter['turfIndex'] = $resp['turfIndex'];
    $turfVoter['attemptedContact'] = $rIndex;
    $this->voters->updateTurfVoter($turfVoter);
    // Results reported by this NL.
    $this->nls->resultsReported($resp['mcid'],$resp['county']);
  }
  
  /** * * * * * * * * * * * * * * * * * * * * * * * * * * * * * *
   * canvassDate
   *
   * @param null $defaultDate
   * @return array
   */
  function canvassDate($defaultDate=NULL): array
  {
    $form_element['date-cell'] = array(
      '#markup'=>" \n ".'<!-- date cell --><div class="canvass_date">',
    );
    
    $form_element['canvassDate'] = array(
      '#type' => 'date',
      '#title' => t('Date of the voter contact'),
    );
  
    if(!empty($defaultDate)) {
      $form_element['canvassDate']['#default_value'] = $defaultDate;
    }
    
    $form_element['date-cell-end'] = array(
      '#markup'=>" \n ".'</div>'
    );
    return $form_element;
  }
  
  /** * * * * * * * * * * * * * * * * * * * * * * * * * * * * * *
   * voterCounts
   *
   * @param $turfInfo
   * @return array
   */
  function voterCounts($turfInfo): array
  {
    $voterCount = $turfInfo['voterCount'];
    $votedCount = $turfInfo['votedCount'];
    //$votedCount = ($votedCount <  10)?'&nbsp;'.$votedCount:$votedCount;
    $attemptedCount = $turfInfo['attemptedCount'];
    $contactedCount = $turfInfo['contactedCount'];
  
    $form_element['counts'] = array (
      '#markup' => "  \n ".'<div class="no-white voter-counts">',
    );
    $percentage = round($votedCount/$voterCount*100,1);
    $attemptedPercentage = round($attemptedCount/$voterCount*100,1);
    $contactedPercentage = round($contactedCount/$voterCount*100,1);
    $voted = $votedCount.' &nbsp;('.$percentage.'%)';
    $attemptedCount = $attemptedCount.' &nbsp;('.$attemptedPercentage.'%)';
    $contactedCount = $contactedCount.' &nbsp;('.$contactedPercentage.'%)';
    /*
    $form_element['voter-count'] = array(
      '#markup' => " \n ".'<span class="voting-counts">
 Voters: &nbsp;&nbsp;&nbsp;'.$voterCount.'<br>
 Voted: &nbsp;&nbsp;&nbsp;&nbsp;'.$votedCount.' &nbsp;('.$percentage.'%)<br>
 Attempted: '.$attemptedCount.' &nbsp;('.$attemptedPercentage.'%)<br>
 Contacted: '.$contactedCount.' &nbsp;('.$contactedPercentage.'%)
 </span>',
    );
  */
    
    $form_element['counts'] = [
      '#markup' => "  \n ".'<div class="no-white voter-counts">',
    ];
  
    $form_element['table-start'] = [
      '#markup' => '<table class="table" ><tbody>',
    ];
    $form_element['row-voters'] = [
      '#markup' => '<tr class="counts-row"><td  class="counts-name" >Voters</td><td class="counts-numbers" >'.$voterCount.'</td>',
    ];
    $form_element['row-voted'] = [
      '#markup' => '<tr class="counts-row"><td class="counts-name">Voted</td><td class="counts-numbers">'.$voted.'</td>',
    ];
    $form_element['row-attempted'] = [
      '#markup' => '<tr class="counts-row"><td class="counts-name">Attempted</td><td class="counts-numbers">'.$attemptedCount.'</td>',
    ];
    $form_element['row-contacted'] = [
      '#markup' => '<tr class="counts-row"><td class="counts-name">Contacted</td><td class="counts-numbers">'.$contactedCount.'</td>',
    ];
    $form_element['table-end'] = [
      '#markup' => '</tbody></table>',
    ];
  
  
    $form_element['counts-end'] = array (
      '#markup' => " \n   ".'</div>',
    );
    
    
    
    $form_element['counts-end'] = array (
      '#markup' => " \n   ".'</div>',
    );
    return $form_element;
  }
  
  /** * * * * * * * * * * * * * * * * * * * * * * * * * * * * * *
   * awardDisplay
   *
   * @param $awards
   * @return array
   */
  function awardDisplay($awards): array
  {
    if(empty($awards['electionCount'])) { return [];}
    $modulePath = Drupal::service('extension.list.module')->getPath(NLP_MODULE);
    $electionCount = $awards['electionCount'];
    $countPosition = ($electionCount > 9)?'double-digit':'single-digit';
    $badge = "/".$modulePath."/img/nlp_award_seal_2.jpg";
    $form_element['award_start'] =  array(
      '#markup'=>" \n ".'<div class="award-box">');
    $nlAward = '
      <div class="badge-container">
        <img src="'.$badge.'" alt="Snow" class="badge-img">';
    $nlAward .= '
        <div class="election-count '.$countPosition.'">'.$electionCount.'</div>';
    $nlAward .= '
      </div><div class="note-container">Number of elections you have been a successful Neighborhood Leader.</div>';
    $form_element['award'] =  array(
      '#markup'=>" \n ".$nlAward);
    $form_element['award_end'] =  array(
      '#markup'=>" \n ".'<div class="end-big-box"></div></div>');
    return $form_element;
  }
  
  /** * * * * * * * * * * * * * * * * * * * * * * * * * * * * * *
   * buildVoterTable
   *
   * Display the list of voters assigned to this NL and show previously
   * reported results.  An HTML table is built to display the voters in grid
   * form for data entry.  THe changes are processed in the validate function.
   *
   * @param  $voters
   * @param  $buildInfo
   * @return array - form element for display of voters or FALSE if error.
   */
  function buildVoterTable($voters, &$buildInfo): array
  {
   
    $config = Drupal::config('nlpservices.configuration');
    $surveyQuestions = $config->get('nlpservices_survey_questions');
    $contactMethods = $buildInfo['contactMethods'];
    $selectedContactMethods = $buildInfo['selectedContactMethods'];
    //nlp_debug_msg('$selectedContactMethods',$selectedContactMethods);
  
    $canvassResponseCodes = $buildInfo['canvassResponseCodes'];
    //$defaultValues = $buildInfo['defaultValues'];
    
    $form_element['voterForm'] = array(
      '#prefix' => " \n" . '<div id="voterForm-div">' . " \n",
      '#suffix' => " \n" . '</div>' . " \n",
      '#type' => 'fieldset',
      '#attributes' => array(
        'style' => array(
          'background-image: none; border:0; padding:0; margin:0; '
          . 'background-color: rgb(255,255,255);'),),
    );
    // Start the table.
    $form_element['voterForm']['table_start'] = array(
      '#markup' => " \n" . '<!-- Data Entry Table -->' .
        " \n" . '<table class="voter-table">',
    );
    
    // Start the body.
    $form_element['voterForm']['body-start'] = array(
      '#markup' => " \n" . '<tbody>',
    );
    
    // Loop through the voters in the turf and create a row with contact
    // information and the data entry form elements.
    reset($voters);
    if ($buildInfo['currentPage'] != 0) {
      $currentVoter = $buildInfo['currentPage'] * $this::DE_PAGE_SIZE;
      for ($index = 0; $index < $currentVoter; $index++) {
        next($voters);
      }
    }
    
    $voterCount = 0;
    
    do {
      $voter = current($voters);
      if (empty($voter)) {
        break;
      }
      next($voters);
      $vanid = $voter['vanid'];
  
      $selectedContactMethod = NULL;
      if(!empty($selectedContactMethods[$vanid])) {
        $selectedContactMethod = $selectedContactMethods[$vanid];
      } elseif (!empty($buildInfo['preferredContactMethod'])) {
        $selectedContactMethod = $buildInfo['preferredContactMethod'];
      }
      
      $nameDisplay = $this->nlp_name_display($voter);
      $contactInfo = $this->contactInfoDisplay($voter);
      $pledge['vanid'] = $vanid;
      //$pledge['firstVoter'] = $voterCount == 0;
      $pledge['nickname'] = $voter['nickname'];
      $pledge['questionArray'] = $surveyQuestions['state'];
      $pledge['defaultValue'] = 0;
      $pledge['selectedContactMethod'] = $selectedContactMethod;
  
      //$pledge['surveyResponseList'] = $surveyResponseList;
      $pledge2vote = $this->pledgeToVoteCell($pledge);
      
      $method['vanid'] = $vanid;
      //$method['firstVoter'] = $voterCount == 0;
      $method['voterCount'] = $voterCount;
      $method['contactMethodOptions'] = $contactMethods;
      /*
      $method['selectedContactMethod'] = NULL;
      if(!empty($selectedContactMethods[$vanid])) {
        $method['selectedContactMethod'] = $selectedContactMethods[$vanid];
      } elseif (!empty($buildInfo['preferredContactMethod'])) {
        $method['selectedContactMethod'] = $buildInfo['preferredContactMethod'];
      }
      */
      $method['selectedContactMethod'] = $selectedContactMethod;
      //nlp_debug_msg('$buildInfo',$buildInfo);
      //nlp_debug_msg('$method',$method);
      $contactMethod = $this->contactMethodCell($method);
      
      //$noVoterContact['firstVoter'] = $voterCount == 0;
      $noVoterContact['voterCount'] = $voterCount;
  
      $noVoterContact['vanid'] = $vanid;
      $buildInfo['vanids'][$voterCount] = $vanid;
      $noVoterContact['historical'] = $voter['historic']['display'];
      $noVoterContact['historicalLines'] = $voter['historic']['displayLines'];
      //$noVoterContact['contactMethod'] = $buildInfo['contactMethod'][$vanid];
      $noVoterContact['selectedContactMethod'] = NULL;
      if(!empty($selectedContactMethods[$vanid])) {
        $noVoterContact['selectedContactMethod'] = $selectedContactMethods[$vanid];
      } elseif (!$buildInfo['preferredContactMethod']) {
        $noVoterContact['selectedContactMethod'] = $buildInfo['preferredContactMethod'];
      }
  
      $countyQuestion = [];
      if(!empty($surveyQuestions[$buildInfo['county']])) {
        $countyQuestion['vanid'] = $vanid;
        $countyQuestion['nickname'] = $voter['nickname'];
        $countyQuestion['countyQuestionArray'] = $surveyQuestions[$buildInfo['county']];
      }
      
      //$countyQuestion['countySurveyResponseList'] = $countySurveyResponseList;
  
      //nlp_debug_msg('$contactMethods',$contactMethods);
      $noVoterContact['contactResponseOptions'] = NULL;
      if(!empty($selectedContactMethods[$vanid])) {
        $voterContactMethod = $contactMethods[$selectedContactMethods[$vanid]];
        $noVoterContact['contactResponseOptions'] = $canvassResponseCodes[$voterContactMethod];
      } elseif (!empty($buildInfo['preferredContactMethod'])) {
        //$noVoterContact['contactResponseOptions'] = $buildInfo['preferredContactMethod'];
        $voterContactMethod = $contactMethods[$buildInfo['preferredContactMethod']];
        $noVoterContact['contactResponseOptions'] = $canvassResponseCodes[$voterContactMethod];
      }

      //nlp_debug_msg('$noVoterContact',$noVoterContact);
      //$optionsDisplay = array('' => 'Select Method');
      $optionsDisplay = NULL;
      if (!empty($noVoterContact['contactResponseOptions'])) {
        //nlp_debug_msg('method: '.$noVoterContact['contactMethod'],$noVoterContact['contactResponseOptions']);

        $responses = $noVoterContact['contactResponseOptions']['responses'];
        $hidden = ['Deceased','Hostile','Moved'];
        foreach ($hidden as $hide) {
            unset($responses[$hide]);
        }
        $optionsDisplay = array_flip($responses);
        array_unshift($optionsDisplay, 'Select response');
        //nlp_debug_msg('optionsDisplay',$optionsDisplay);
      }
      $noVoterContact['optionsDisplay'] = $optionsDisplay;
      $buildInfo['optionsDisplay'][$vanid] = $noVoterContact['optionsDisplay'];
  
  
      //nlp_debug_msg('$noVoterContact',$noVoterContact);
      
      //$buildInfo['contactResponseOptions'][$vanid] = $noVoterContact['contactResponseOptions'];
  
      //$noVoterContact['contactResponseOptions'] = $selectedCodes;
      $noContact = $this->noContactCell($noVoterContact,$countyQuestion);
      
      //$notRight['firstVoter'] = $voterCount == 0;
      $notRight['vanid'] = $vanid;
      
      $notRight['deceased'] = $voter['status']['deceased'];
      $buildInfo['defaultValues'][$vanid]['deceased'] = $notRight['deceased'];
  
      $notRight['hostile'] = $voter['status']['hostile'];
      $buildInfo['defaultValues'][$vanid]['hostile'] = $notRight['hostile'];
  
      $notRight['moved'] = $voter['address']['moved'];
      $buildInfo['defaultValues'][$vanid]['moved'] = $notRight['moved'];
  
      $notRight['cellPhone'] = $voter['cellPhone'];
      $notRight['homePhone'] = $voter['homePhone'];
      $somethingsWrong = $this->somethingsWrongCell($notRight);
      
      $votingStatus = $this->votingStatusCell($voter,$voterCount);
      
      $noteText = html_entity_decode($voter['turfVoter']['note'], ENT_QUOTES );
      if(!empty($noteText)) {
        $buildInfo['defaultValues'][$vanid]['note'] = $noteText;
        $buildInfo['defaultValues'][$vanid]['reportIndex'] = $voter['turfVoter']['reportIndex'];
      }
      $note = $this->noteCell($vanid,$noteText);
      $canvassStatus = $this->canvassStatus($voter,$countyQuestion);

// = = = = = = = = = First row.
      // Use the Drupal class for odd/even table rows and start the row.
      $form_element['voterForm']["rowA-start-$vanid"] = array(
        '#markup' => t(" \n" . '<tr class="odd">' . '<!-- ' . $nameDisplay['name'] . ' row -->'),
        '#prefix' => t('<div class="voter-name">'),
        '#suffix' => t('</div>'),
        );
// name cell.
      $form_element['voterForm']["cellA0-$voterCount"] = array(
        '#markup' => t(" \n " . '<td class="td-de" colspan="5">' . $nameDisplay['nameDisplay'] . '</td>'),
        '#prefix' => t('<div class="voter-name">'),
        '#suffix' => t('</div>'),
      );
      // End the name row.
      $form_element['voterForm']["rowA-end-$voterCount"] = array(
        '#markup' => " \n" . '</tr>',
      );
// = = = = = = = = = Second row.
      $form_element['voterForm']["row0-start-$vanid"] = array(
        '#markup' => " \n" . '<tr class="odd">',);
// address cell.
      $form_element['voterForm']["cell00-$voterCount"] = array(
        '#markup' => " \n " . '<td class="td-de">' . $contactInfo . '</td>',
      );
// voter contact method cell.
      $form_element['voterForm']["cell01-$voterCount-start"] = array(
        '#markup' => " \n " . '<td class="td-de" style="position:relative;" rowspan="2">',
      );
      $form_element['voterForm']["cell01-$voterCount-body"] = $contactMethod;
      $form_element['voterForm']["cell01-$voterCount-end"] = array(
        '#markup' => " \n " . '</td>',
      );
// pledge to vote cell.
      $form_element['voterForm']["cell02-$voterCount-start"] = array(
        '#markup' => " \n " . '<td class="td-de" style="position:relative;" rowspan="2">',
      );
      $form_element['voterForm']["cell02-$voterCount-body"] = $pledge2vote;
      $form_element['voterForm']["cell02-$voterCount-end"] = array(
        '#markup' => " \n " . '</td>',
      );
//  no contact cell.
      $form_element['voterForm']["cell03-$voterCount-start"] = array(
        '#markup' => " \n " . '<td class="td-de" rowspan="3" style="position:relative; ">',
      );
      $form_element['voterForm']["cell03-$voterCount-body"] = $noContact;
      $form_element['voterForm']["cell03-$voterCount-end"] = array(
        '#markup' => " \n " . '</td>',
      );
// something's wrong cell.
      $form_element['voterForm']["cell04-$voterCount-start"] = array(
        '#markup' => " \n " . '<td class="td-de" rowspan="3" style="position:relative;">',
      );
      $form_element['voterForm']["cell04-$voterCount-body"] = $somethingsWrong;
      $form_element['voterForm']["cell04-$voterCount-end"] = array(
        '#markup' => " \n " . '</td>',
      );
      // End the second row.
      $form_element['voterForm']["row0-end-$voterCount"] = array(
        '#markup' => " \n" . '</tr>',
      );

// = = = = = = = = = New Third row.
      $form_element['voterForm']["rowB-start-$vanid"] = array(
        '#markup' => " \n" . '<tr class="odd">',);
// voting status cell.
      $form_element['voterForm']["cellB0-$voterCount-start"] = array(
        '#markup' => " \n " . '<td class="td-de" rowspan="2" >',
      );
      $form_element['voterForm']["cellB0-$voterCount-body"] = $votingStatus;
      $form_element['voterForm']["cellB0-$voterCount-end"] = array(
        '#markup' => " \n " . '</td>',
      );

      // end of new third row.
      $form_element['voterForm']["rowB-end-$voterCount-end"] = array(
        '#markup' => " \n" . '</tr>',
      );

// = = = = = = = = = Third row.
      $form_element['voterForm']["row2-start-$vanid"] = array(
        '#markup' => " \n" . '<tr class="odd">',);
      /*
// voting status cell.
      $form_element['voterForm']["cell20-$voterCount-start"] = array(
        '#markup' => " \n " . '<td class="td-de" >',
      );
      $form_element['voterForm']["cell20-$voterCount-body"] = $votingStatus;
      $form_element['voterForm']["cell20-$voterCount-end"] = array(
        '#markup' => " \n " . '</td>',
      );
      */
// note cell.
      $form_element['voterForm']["cell21-$voterCount-start"] = array(
        '#markup' => " \n " . '<td class="td-de" colspan="2" style="position:relative;">',
      );
      $form_element['voterForm']["cell21-$voterCount-body"] = $note;
      $form_element['voterForm']["cell21-$voterCount-end"] = array(
        '#markup' => " \n " . '</td>',
      );
      // end of third row.
      $form_element['voterForm']["row2-end-$voterCount-end"] = array(
        '#markup' => " \n" . '</tr>',
      );
// = = = = = = = = = Fourth row.
      $form_element['voterForm']["row3-start-$vanid"] = array(
        '#markup' => " \n" . '<tr class="odd">',);
// status cell.
      $form_element['voterForm']["cell30-$vanid-start"] = array(
        '#markup' => " \n " . '<td class="td-de" colspan="5">',
      );
      $form_element['voterForm']["cell30-$vanid-body"] = $canvassStatus;
      $form_element['voterForm']["cell30-$vanid-end"] = array(
        '#markup' => " \n " .'</td>',
      );
      // end of row.
      $form_element['voterForm']["row3-end-$voterCount"] = array(
        '#markup' => " \n" . '</tr>',
      );
// = = = = = = = = = Blank row for separator.
      $form_element['voterForm']["blank-line-$vanid"] = array(
        '#markup' => " \n" . '<tr class="even"><td class="td-de" colspan="5">&nbsp;</td></tr>',
      );
      $voterCount++;
      if ($voterCount == 10) {
        break;
      }
    } while (TRUE);
    // End of the table.
    $form_element['voterForm']['table_end'] = array(
      '#markup' => " \n" . '</tbody>' . " \n" . '</table>' . " \n" . '<!-- End of Data Entry Table -->' . " \n",
    );
    return $form_element;
  
  }

  /** * * * * * * * * * * * * * * * * * * * * * * * * * * * * * *
   * fetchVoters
   *
   * Build the array of voter information for the voters in this NL's turf.
   * Also, get the precinct, HD and CD numbers for this turf.  These numbers
   * should all be the same but with shifting registration, one or more might
   * change.  So, look for at least three precincts that are the same and assume
   * these are the numbers.
   *
   * @param $turfIndex
   * @param $voters
   * @return array - of voter information for this turf or FALSE if error.
   */
  function fetchVoters($turfIndex,&$voters): array
  {
    //nlp_debug_msg('$turfIndex',$turfIndex);
    $votersInTurf = $this->voters->fetchVotersByTurf($turfIndex);
    //nlp_debug_msg('$votersInTurf',$votersInTurf);
    $voterCount = $precinctCount = $votedCount = $attemptedCount = $contactedCount = 0;
    $hd = $pct = $cd = NULL;
    foreach ($votersInTurf as $voterInfo) {
      $vanid = $voterInfo['vanid'];
      $voterStatus = $this->voters->getVoterStatus($vanid);

      $voterInfo['status'] = $voterStatus;
      if(!empty($voterStatus['voted'])) {
        $votedCount++;
      }
      
      $voterContacts = $this->reports->getNlpReports($vanid);
      //nlp_debug_msg('$voterContacts',$voterContacts);
      $contacted = $attempted = false;
      foreach ($voterContacts as $voterContactList) {
        foreach ($voterContactList as $voterContact) {
          //nlp_debug_msg('$voterContact',$voterContact);
          //nlp_debug_msg('$voterContact[type]',$voterContact['type']);
          if($voterContact['type'] == $this->reports::SURVEY) {
            $attempted = $contacted = TRUE;
            break;
          } elseif ($voterContact['type'] == $this->reports::CONTACT) {
            $attempted = TRUE;
            //nlp_debug_msg('$voterContact',$voterContact);
          }
        }
      }
      if($attempted) {$attemptedCount++;}
      if($contacted) {$contactedCount++;}
  
      //nlp_debug_msg('reports: '.$vanid,$voterContacts);
      $voterAc = $this->reports->getNlpAcReport($vanid);
      $display = $this->reports->displayNlReports($voterContacts);
      //nlp_debug_msg('display',$display);
      $voterInfo['historic'] = $display;
      $voterInfo['activist'] = $voterAc;
      
      // Save the first precinct and HD unless it is different from the second,
      // and we have seen at least three that are the same.
      if($pct==NULL) {
        $hdCurrent = $voterInfo['address']['hd'];
        $precinctCurrent = $voterInfo['address']['precinct'];
        $cdCurrent = $voterInfo['address']['cd'];
        if($precinctCount == 0) {
          $hd = $hdCurrent;
          $pct = $precinctCurrent;
          $cd = $cdCurrent;
        } elseif ($precinctCount<3 AND $pct!=$precinctCurrent) {
          $hd = $hdCurrent;
          $pct = $precinctCurrent;
          $cd = $cdCurrent;
          $precinctCount = 0;
        }
      }
      $precinctCount++;
      $voters[$vanid] = $voterInfo;
      $voterCount++;
    }
    // Save the turf HD and Precinct for the list of candidates to display.
    $turfInfo['turf-hd'] = $hd;
    $turfInfo['turf-pct'] = $pct;
    $turfInfo['turf-cd'] = $cd;
    $turfInfo['voterCount'] = $voterCount;
    $turfInfo['votedCount'] = $votedCount;
    $turfInfo['attemptedCount'] = $attemptedCount;
    $turfInfo['contactedCount'] = $contactedCount;
    $turfInfo['pageCount'] = (int) ceil($voterCount/10);
    return $turfInfo;
  }
  
  /** * * * * * * * * * * * * * * * * * * * * * * * * * * * * * *
   * formatTelephone
   *
   * Convert the phone number into a standard form.
   *
   * @param  $phoneNumber
   * @return string
   */
  function formatTelephone($phoneNumber): string
  {
    if(empty($phoneNumber)) {return'';}
    $cleaned = preg_replace('/[^[:digit:]]/', '', $phoneNumber);
    $matches = [];
    preg_match('/(\d{3})(\d{3})(\d{4})/', $cleaned, $matches);
    return "($matches[1]) $matches[2]-$matches[3]";
  }
  
  /** * * * * * * * * * * * * * * * * * * * * * * * * * * * * * *
   * nlp_name_display
   *
   * @param $voter
   * @return array
   */
  function nlp_name_display($voter): array
  {
    $name = $voter['lastName'].", ".$voter['firstName'];
    $nameDisplay = '<div class="voter-name">'.$name;
    if(!empty($voter['nickname']) AND $voter['nickname'] != $voter['firstName']) {
      $nameDisplay .= " (".$voter['nickname'].")";
    }
    $nameDisplay .= '</div><div class="voter-info">'.'&nbsp; - Age:'.$voter['age'].' Gender:'.$voter['sex'].' Party:'.$voter['party'].'</div>';
    return array('name'=>$name, 'nameDisplay'=>t($nameDisplay));
  }
  
  /** * * * * * * * * * * * * * * * * * * * * * * * * * * * * * *
   * contactInfoDisplay
   *
   * @param $voter
   * @return string
   */
  function contactInfoDisplay($voter): string
  {
    $addressString = $voter['address']['streetNo']." "
      .$voter['address']['streetPrefix']." ".$voter['address']['streetName']
      ." ".$voter['address']['streetType']." ".$voter['address']['aptType']
      ." ".$voter['address']['aptNo']."<br/>".$voter['address']['city'];
    $phones = "<br/>";
    if(empty($voter['homePhone']) AND empty($voter['cellPhone'])) {
      $phones .= "<i>No phone numbers.</i>";
    }
    if (!empty($voter['homePhone'])) {
      $phones .= "H: ".$this->formatTelephone($voter['homePhone']);
      if($voter['preferredPhoneType'] == 'H') {
        $phones .= ' <span style="font-size: xx-small; font-style: italic; ">Preferred</span>';
      }
    }
    if (!empty($voter['cellPhone'])) {
      if (!empty($voter['homePhone'])) {
        $phones .= "<br/>";
      }
      $phones .= "C: ".$this->formatTelephone($voter['cellPhone']);
      if($voter['preferredPhoneType'] == 'C') {
        $phones .= ' <span style="font-size: xx-small; font-style: italic; ">Preferred</span>';
      }
    }
    return $addressString.$phones;
  }
  
  /** * * * * * * * * * * * * * * * * * * * * * * * * * * * * * *
   * contactMethodCell
   *
   * @param $method
   * @return array
   */
  function contactMethodCell($method): array
  {
    $vanid = $method['vanid'];
    $form_element = array();
    
    $form_element['start'] = array(
      '#markup' => " \n ".'<div><b>Contact Method</b> ',
    );
    /*
    $form_element["contact_method-$vanid"] = array(
      '#type' => 'select',
      '#options' => $method['contactMethodOptions'],
      '#ajax' => array(
        'callback' => '::row'.$method['voterCount'].'Callback',
        'wrapper' => 'no_contact_row'.$method['voterCount'].'_div',
      ),
    );
    */
    $form_element["contact_method-$vanid"] = array(
      '#type' => 'select',
      '#options' => $method['contactMethodOptions'],
      '#ajax' => array(
        'callback' => '::formCallback',
        'wrapper' => 'voterForm-div',
      ),
    );
  
  
    $postcardSend = $this->reports->voterSentPostcard($vanid);
    $form_element["postcardMailed-$vanid"] = array(
      '#type' => 'checkbox',
      '#title' => 'A postcard was mailed.',
      '#default_value' => $postcardSend,
      '#prefix' => '<div class="line-spacer" title="Selecting this option is the same as selecting both the method
and the response.  It can\'t be undone">',
      '#suffix' => '</div><div class="line-spacer"></div>',
    );
  
  
    if(!empty($method['selectedContactMethod'])) {
      $form_element["contact_method-$vanid"]['#default_value'] =  $method['selectedContactMethod'];
    } else {
      $form_element['method_hint'] = array(
        '#markup' => " \n ".'<div><i>You must select a Contact Method <br>
 before you can report a voter contact response.</i> ',
      );
    }
    $form_element['end'] = array(
      '#markup' => " \n ".'</div>',
    );
    return $form_element;
  }
  
  /** * * * * * * * * * * * * * * * * * * * * * * * * * * * * * *
   * pledgeToVoteCell
   *
   * @param array $pledge
   * @return array
   */
  function pledgeToVoteCell(array $pledge): array
  {
    $form_element = array();
    
    if (!empty($pledge['questionArray'])) {
      
      if(empty($pledge['selectedContactMethod'])) {
  
        $form_element['title'] = array(
          '#markup' => t(" \n "
            . '<div class="response-title">Voter Responded<br></div>'
            . '<i>Select a Contact Method first.<br></i>' . '</div>'),
        );
      } else {
        //nlp_debug_msg('questionArray',$pledge['questionArray']);
        $question = strip_tags(t($pledge['questionArray']['scriptQuestion'],
          array(':fn' => $pledge['nickname'])));
        $form_element['title'] = array(
          '#markup' => t(" \n "
            .'<div class="response-title">Voter Responded<br></div>'
            . $question . '</div>'),
        );
        $responseList = [0=>'Select Response'];
        foreach ($pledge['questionArray']['responses'] as $responseId=>$response) {
          $responseList[$responseId] = $response['name'];
        }
  
        $responseList = str_replace(":fn", $pledge['nickname'], $responseList);
        $form_element["pledge2vote-" . $pledge['vanid']] = array(
          '#type' => 'select',
          '#options' => $responseList,
          '#default_value' => $pledge['defaultValue'],
          '#prefix' => '<div>',
          '#suffix' => '</div>',
        );
        $form_element['pledge_hint'] = array(
          '#markup' => t(" \n "
            .'<div class="response-title-note line-spacer">
            (Skip this report unless voter responds directly.)</div>'),
        );
      }
      
    }
    return $form_element;
  }
  
  /** * * * * * * * * * * * * * * * * * * * * * * * * * * * * * *
   * noContactCell
   *
   * @param $noVoterContact
   * @param $countyQuestion
   * @return array
   */
  function noContactCell($noVoterContact,$countyQuestion=NULL): array
  {
    $form_element = array();
    if(!empty($countyQuestion['countyQuestionArray'])) {
      $question = strip_tags(t($countyQuestion['countyQuestionArray']['scriptQuestion'], array(':fn' => $countyQuestion['nickname'])));
      $form_element['county_question_title'] = array(
        '#markup' => t(" \n "
          . '<div class="response-title line-spacer">Voter Responded<br></div>'
          .'<div class="line-spacer">'
          . $question . '</div>'),
      );
      //nlp_debug_msg('$countyQuestion',$countyQuestion);
      $responseList = [0=>'Select Response'];
      foreach ($countyQuestion['countyQuestionArray']['responses'] as $responseId=>$response) {
        $responseList[$responseId] = $response['name'];
      }
      $responseList = str_replace(":fn", $countyQuestion['nickname'], $responseList);
      $form_element["county_question-" . $countyQuestion['vanid']] = array(
        '#type' => 'select',
        '#options' => $responseList,
        '#prefix' => '<div>',
        '#suffix' => '</div><div class="line-spacer"></div>',
      );
    }
    $vanid = $noVoterContact['vanid'];
    $form_element['note'] = array(
      '#markup' => '<b>No Voter Response</b>',
    );
    if(empty($noVoterContact['optionsDisplay'])) {
      $voterCount = $noVoterContact['voterCount'];
      $form_element['notice'] = array(
        '#markup' => '<i>Select a Contact Method first.<br></i>',
        '#prefix' => '<div id="no_contact_row'.$voterCount.'_div">',
        '#suffix' => '</div>',
      );
    } else {
      $optionsDisplay = $noVoterContact['optionsDisplay'];
      $voterCount = $noVoterContact['voterCount'];
      $form_element["no_contact-".$voterCount] = array(
        '#type' => 'select',
        '#options' => $optionsDisplay,
        '#prefix' => '<div id="no_contact_row'.$voterCount.'_div">',
        '#suffix' => '</div>',
      );
    }
  
    /*
    $postcardSend = $this->reports->voterSentPostcard($vanid);
    $form_element["postcardMailed-$vanid"] = array(
      '#type' => 'checkbox',
      '#title' => 'A postcard was mailed.',
      '#default_value' => $postcardSend,
      '#prefix' => '<div class="line-spacer">',
      '#suffix' => '</div><div class="line-spacer"></div>',
    );
    */
    
    $form_element['historical-start'.$vanid] = array(
      '#markup' => '<div>',
    );
    $historicalContacts = '<span class="historical-report-list">'
      . $noVoterContact['historical'] . '</span>';
    if (!empty($noVoterContact['historical'])) {
      $form_element['historical'] = array(
        '#markup' => '<div class="line-spacer"></div><div class="hint_note historical-report-title">'
          . '<p><br>View historical contacts' . $historicalContacts . '</p></div>',
      );
    }
    $form_element['historical-end'.$vanid] = array(
      '#markup' => '</div>',
    );
    return $form_element;
  }
  
  /** * * * * * * * * * * * * * * * * * * * * * * * * * * * * * *
   * somethingsWrongCell
   *
   * @param $notRight
   * @return array
   */
  function somethingsWrongCell($notRight): array
  {
    $vanid = $notRight['vanid'];
    $form_element = array();
    $form_element['title'] = array(
      '#markup' => '<div><b>Something went wrong:</b> </div>',
    );
    $form_element["moved-$vanid-start"] = array(
      '#type' => 'checkbox',
      '#title' => 'Moved',
      '#default_value' => $notRight['moved'],
    );
    
    $form_element["hostile-$vanid-start"] = array(
      '#type' => 'checkbox',
      '#title' => 'Hostile',
      '#default_value' => $notRight['hostile'],
    );
    
    $form_element["deceased-$vanid-start"] = array(
      '#type' => 'checkbox',
      '#title' => 'Deceased',
      '#default_value' => $notRight['deceased'],
      '#suffix' => '<div class="line-spacer"></div>',
    );
    
    $contactUpdate = array(''=>'Report something');
    if(!empty($notRight['cellPhone'])) {
      $contactUpdate['BC'] = 'Bad No. '.$notRight['cellPhone'];
    }
    if(!empty($notRight['homePhone'])) {
      $contactUpdate['BH'] = 'Bad No. '.$notRight['homePhone'];
    }
    $contactUpdate['NC'] = 'New Cell Number';
    $contactUpdate['NH'] = 'New Home Number';
    $contactUpdate['NE'] = 'New Email';
    $form_element["contact_update-$vanid-type"] = array(
      '#type' => 'select',
      '#title' => 'Update Contact Info.',
      '#options' => $contactUpdate,
    );
    
    $form_element["contact_update-$vanid-value"] = array(
      '#type' => 'textfield',
      '#size' => 20,
      '#maxlength' => 128,
      '#prefix' => '<div title="This text box is to report a new phone number or email.
It is not for general comments.">',
      '#suffix' => '</div>',
    );
    return $form_element;
  }
  
  /** * * * * * * * * * * * * * * * * * * * * * * * * * * * * * *
   * votingStatusCell
   *
   * @param $voter
   * @param $voterCount
   * @return array
   */
  function votingStatusCell($voter,$voterCount): array
  {
    $yes = '<span class="yes">Y</span> ';
    $no = '<span class="no">N</span> ';
    $formattedVotingHistory = str_replace(array('@Y','@N'),array($yes,$no),$voter['votingHistory']);
    if(!empty($voter['localElection'])) {
      $formattedVotingHistory .= '<br>'.$voter['localElection'];
    }
    $votingRecord = '<div><b>Voting Record</b><br>'.$formattedVotingHistory.'<br></div>';

    $star = '<div><span class="not-voted">'.$voter['nickname']." hasn't voted yet.<br></span></div>";
    if(!empty($voter['status']['voted'])) {
      $votedMsg = ' Success! </br>'.$voter['nickname'].' voted on '.$voter['status']['voted'];
      $color = 'green';
      $modulePath = Drupal::service('extension.list.module')->getPath(NLP_MODULE);
      //$goldStar = "/sandbox/web/".$modulePath."/img/nlp-gold-star_rotator-50.gif";
      $goldStar = $modulePath."/img/nlp-gold-star_rotator-50.gif";

      //nlp_debug_msg('$modulePath',$modulePath);
      //nlp_debug_msg('$goldStar',$goldStar);
      //nlp_debug_msg('$_SERVER',$_SERVER);
      $star = '
<div class="star-box">
  <div class="star-img"><img alt="" src="'.$goldStar.'" ></div>
  <div class="star-text"><span style="color:'.$color.'; font-weight: bold; ">'.$votedMsg.'</span></div>
</div><div class="end-big-box"></div>';
    }
    
    $form_element["cell20-$voterCount-star"]['star'] = array(
      '#markup' => t($star),
    );

    $form_element["cell20-$voterCount-record"]['note'] = array(
      '#markup' => t($votingRecord),
    );

    return $form_element;
  }
  
  /** * * * * * * * * * * * * * * * * * * * * * * * * * * * * * *
   * noteCell
   *
   * @param $vanid
   * @param $note
   * @return array
   */
  function noteCell($vanid,$note): array
  {
    //$nlpReportsObj = new NlpReports();
    $commentMax = $this->reports::MAX_COMMENT;
    $form_element['title'] = array(
      '#markup' => " \n "
        . '<div style="padding-bottom:4px; margin-bottom:0;">Your (optional) note about this voter ('.$commentMax.' characters max).'
        . '</div>',
    );
    $form_element["note-".$vanid] = array(
      '#type' => 'textarea',
      '#rows' => 1,
      '#cols' => 13,
      '#default_value' => $note,
    );
    return $form_element;
  }
  
  /** * * * * * * * * * * * * * * * * * * * * * * * * * * * * * *
   * canvassStatus
   *
   * @param $voter
   * @param $countyQuestion
   * @return array
   */
  function canvassStatus($voter,$countyQuestion): array
  {
    $statusMessage = [
      'deceased' => ['class'=>'#voter-deceased', 'text'=>':fn has been marked as deceased.'],
      'hostile' => ['class'=>'voter-hostile', 'text'=>':fn has been marked as hostile and will be removed from future turfs.  Do not attempt to contact this voter again.'],
      'moved' => ['class'=>'voter-moved', 'text'=>':fn has moved.'],
      'pledge' => ['class'=>'voter-pledge', 'text'=>'On :contactDate you asked :fn to pledge to vote. The response was - :response.'],
      'county' => ['class'=>'voter-county', 'text'=>':scriptQuestion.  On :contactDate the response was - :response'],
      'postcard' => ['class'=>'voter-postcard', 'text'=>'On :contactDate you sent :fn a postcard.'],
      'attempt' => ['class'=>'voter-attempt', 'text'=>'On :contactDate you attempted to contact :fn.  The result was - :response'],
      'noAttempt' => ['class'=>'voter-no-attempt', 'text'=>'You have not yet attempted to contact :fn.'],
    ];
    $contactDate = $scriptQuestion = $response = NULL;
    $fn = $voter['nickname'];
    $dateFormatter = Drupal::service('date.formatter');

    //nlp_debug_msg('turfVoter',$voter['turfVoter']);

    $vanid = $voter['vanid'];
    if(!empty($voter['status']['deceased'])) {
      $contactStatusType = 'deceased';
    } elseif(!empty($voter['status']['hostile'])) {
      $contactStatusType = 'hostile';
    } elseif($voter['address']['moved']) {
      $contactStatusType = 'moved';
    } elseif (!empty($voter['turfVoter']['pledgedToVote'])) {
      $rIndex = $voter['turfVoter']['pledgedToVote'];
      $pledgeReport = $this->reports->getNlpReport($rIndex);
      //$response = $pledgeReport['value'];
      $response = '['.$pledgeReport['contactType'].'] '.$pledgeReport['value'];
      //nlp_debug_msg('$pledgeReport',$pledgeReport);
      $dateTime = new DrupalDateTime($pledgeReport['contactDate'], new DateTimeZone('UTC'));
      $timestamp = $dateTime->getTimestamp();
      $contactDate = $dateFormatter->format($timestamp, 'custom' ,'M j');
      $contactStatusType = 'pledge';
    } elseif (!empty($voter['turfVoter']['countyQuestion'])) {
      $rIndex = $voter['turfVoter']['countyQuestion'];
      $countyQuestionReport = $this->reports->getNlpReport($rIndex);
      //nlp_debug_msg('$countyQuestionReport',$countyQuestionReport);
      $dateTime = new DrupalDateTime($countyQuestionReport['contactDate'], new DateTimeZone('UTC'));
      $timestamp = $dateTime->getTimestamp();
      $contactDate = $dateFormatter->format($timestamp, 'custom' ,'M j');
      $scriptQuestion = $countyQuestion['countyQuestionArray']['scriptQuestion'] ;
      $contactStatusType = 'county';
    } elseif(!empty($voter['turfVoter']['attemptedContact'])) {
      $rIndex = $voter['turfVoter']['attemptedContact'];
      $attemptReport = $this->reports->getNlpReport($rIndex);
      //nlp_debug_msg('$attemptReport',$attemptReport);
      $dateTime = new DrupalDateTime($attemptReport['contactDate'], new DateTimeZone('UTC'));
      $timestamp = $dateTime->getTimestamp();
      $contactDate = $dateFormatter->format($timestamp, 'custom' ,'M j');
      if($attemptReport['value'] == 'Postcard') {
        $contactStatusType = 'postcard';
      } else {
        $contactStatusType = 'attempt';
        $response = '['.$attemptReport['contactType'].'] '.$attemptReport['value'];
      }
    } else {
      $contactStatusType = 'noAttempt';
    }
  
    $rawMsg = $preMsg = $statusMessage[$contactStatusType]['text'];
    //nlp_debug_msg('$contactStatusMsg',$contactStatusMsg);

    //nlp_debug_msg('$scriptQuestion',$scriptQuestion);
    //nlp_debug_msg('$response',$response);
    if(!empty($scriptQuestion) OR !empty($response)) {
      $preMsg = (string) t($rawMsg, [':scriptQuestion'=>$scriptQuestion, ':response'=>$response]);
    }
    //nlp_debug_msg('$preMsg',$preMsg);

    $contactStatusMsg = t($preMsg,[':fn'=>$fn, ':contactDate'=>$contactDate]);
    //nlp_debug_msg('$contactStatusMsg',$contactStatusMsg);
    
    $form_element["status-$vanid-contact"] = array(
      '#markup' => t('<div class="'.$statusMessage[$contactStatusType]['class'].'">'.$contactStatusMsg.'</div>'),
    );
    return $form_element;
  }
  
  /** * * * * * * * * * * * * * * * * * * * * * * * * * * * * * *
   * createDateBar
   *
   * @param $canvassDate
   * @param $turfInfo
   * @param $awards
   * @param $currentPage
   * @return array
   */
  function createDateBar($canvassDate, $turfInfo, $awards, $currentPage): array {
    $form['date_bar'] = [
      '#markup' => '<div class="date-bar">',
    ];
  
    $form['date_box'] = [
      '#markup' => '<div class="date-box-left">',
    ];
    $defaultDate = $canvassDate;
    //nlp_debug_msg('$defaultDate',$defaultDate);
  
    $form['canvass_date'] = $this->canvassDate($defaultDate);
    $form['date_box_end'] = [
      '#markup' => '</div>',
    ];
    $form['counts_box'] = [
      '#markup' => '<div class="counts-box-left">',
    ];
    //$form['voter_counts'] = $this->voterCounts($turfInfo['voterCount'],$turfInfo['votedCount']);
    $form['voter_counts'] = $this->voterCounts($turfInfo);
    $form['date_counts_end'] = [
      '#markup' => '</div>',
    ];
  
    $form['award_box'] = [
      '#markup' => '<div class="awards-box-left">',
    ];
    $form['award'] = $this->awardDisplay($awards);
    $form['award_end'] = [
      '#markup' => '</div>',
    ];
  
    $form['search_box'] = [
      '#markup' => '<div class="search-box-left">',
    ];
    $form['search'] = $this->searchRequest();
    $form['search_end'] = [
      '#markup' => '</div>',
    ];
  
    $form['nav_box'] = [
      '#markup' => '<div class="nav-box-right">',
    ];
    $form['nav'] = $this->navigate($turfInfo['voterCount'],$turfInfo['pageCount'],$currentPage);
    $form['nav_end'] = [
      '#markup' => '</div>',
    ];
  
    $form['date_bar_end'] = [
      '#markup' => '</div><div class="end-big-box"></div>',
    ];
    return $form;
  }
  
  /** * * * * * * * * * * * * * * * * * * * * * * * * * * * * * *
   * navigate
   *
   * Build the buttons for navigation through the data entry pages.
   *
   * @param  $voterCount - total count of voters.
   * @param  $pageCount - number of pages for this turf.
   * @param  $currentPage - current page bing viewed.
   * @return array of form elements.
   */
  function navigate($voterCount,$pageCount,$currentPage): array
  {
    //nlp_debug_msg('$voterCount',$voterCount);
    $currentPage++;
    if($pageCount < 7) {
      $page = 1;
      $elementCount = $pageCount;
      $less = $more = FALSE;
    } elseif ($currentPage < 7) {
      $page = 1;
      $elementCount = 6;
      $less = FALSE;
      $more = TRUE;
    } else {
      $page = 7;
      $elementCount = $pageCount-6;
      $less = TRUE;
      $more = FALSE;
    }
  
    $form_element['navigation_box'] = array (
      '#markup' => "  \n ".'<section class="nav_box no-white">',
    );
  
    
    $form_element['nav'] = array(
      '#type' => 'fieldset',
      '#prefix' => " \n".'<div class="nav_div">'." \n",
      '#suffix' => " \n".'</div>'." \n",
      '#attributes' => array(
        'style' => array('background-image: none; border:0; padding:0; margin:0; '),),
    );
    
    if($less) {
      $form_element['nav']['previous'] = array(
        '#type' => 'submit',
        '#value' => '< Previous',
        '#name' => 'previous',
        '#prefix' => " \n".'<div class="nav_number" >'." \n",
        '#suffix' => " \n".'</div>'." \n",
      );
      $form_element['nav']['dots'] = array(
        '#markup' => ' ... ',
        '#prefix' => " \n".'<div class="nav_dots" >'." \n",
        '#suffix' => " \n".'</div>'." \n",
      );
    }
  
  
    for ($element=1; $element<=$elementCount; $element++) {
      //$pageName = $formPageName[$element];
    
      if($page == $currentPage) {
        $hoverMessage = "You are on page $page.";
        $pageClass = 'nav_current_number';
      } else {
        $hoverMessage = "Go to page $page.";
        $pageClass = 'nav_number';
      }
      $form_element['nav']['pageSelect-'.$page] = [
        '#type' => 'submit',
        '#value' => $page,
        '#name' => 'pageSelect-'.$page,
        '#prefix' => " \n".'<div class="'.$pageClass.'" title="'.$hoverMessage.'">'." \n",
        '#suffix' => " \n".'</div>'." \n",
      ];
      $page++;
    }
  
    if($more) {
      $form_element['nav']['dots'] = array(
        '#markup' => ' ... ',
        '#prefix' => " \n".'<div class="nav_dots" >'." \n",
        '#suffix' => " \n".'</div>'." \n",
      );
      $form_element['nav']['next'] = array(
        '#type' => 'submit',
        '#value' => 'Next >',
        '#name' => 'next',
        '#prefix' => " \n".'<div class="nav_number" >'." \n",
        '#suffix' => " \n".'</div>'." \n",
      );
    }
  
    $form_element['nav']['save_reports'] = array(
      '#type' => 'submit',
      '#value' => 'Save reports',
      '#name' => 'save_reports',
      '#prefix' => " \n".'<div class="nav_number" title="And stay on this page.">'." \n",
      '#suffix' => " \n".'</div>'." \n",
    );
  
    $form_element['navigation_end'] = array (
      '#type' => 'markup',
      '#markup' => " \n   ".'</section>',
    );
   
    return $form_element;
  }
  
  /** * * * * * * * * * * * * * * * * * * * * * * * * * * * * * *
   * searchRequest
   *
   * @return array
   */
  function searchRequest(): array
  {
    $form_element['search-cell'] = ['#markup'=>" \n ".'
 <!-- search --><section class="search-box">',];
    $form_element['search-name'] = ['#markup' => '
<table class="no-white search"><tbody class="no-white">
<tr class="no-white white-back"><td class="no-white" colspan="2">
<div class="no-white">Search by last name.</div>
</td></tr>'];
    $form_element['search-header'] = ['#markup' => '
<tr class="no-white white-back"><td class="no-white">'];
  
    $form_element['last-name'] = array(
      '#type' => 'textfield',
      '#decription' => 'Last name.',
      '#size' => 20,
      '#maxlength' => 40,
      //'#attributes' => array('class' => array('no-white')),
    );
    
    $form_element['search-submit'] = ['#markup' => '</td><td class="no-white">'];
  
    $form_element['last_name_submit'] = array (
      '#type' => 'submit',
      '#name' => 'last_name_search',
      '#value' => 'Search',
    );
    $form_element['search-name-end'] = ['#markup' => '</td></tr></tbody></table>'];
  
    $form_element['search-cell-end'] = ['#markup'=>" \n ".'</section>'];
  
    return $form_element;
  }
  
  /** * * * * * * * * * * * * * * * * * * * * * * * * * * * * * *
   * voterSearch
   *
   * @param $turfIndex int
   * @param $needle string
   * @return array
   */
  function voterSearch(int $turfIndex, string $needle): array {
    $this->fetchVoters($turfIndex,$voters);
    $lcNeedle = strtolower($needle);
    //nlp_debug_msg('$lcNeedle',$lcNeedle);
    $voterIndex = $page = 0;
    $found = FALSE;
    foreach($voters as $voter) {
      $lcLastName = strtolower( $voter['lastName']);
      //('$lcLastName',$lcLastName);
      if(str_contains($lcLastName,$lcNeedle)) {
        $found = TRUE;
        break;
      }
      $voterIndex++;
    }
    if($found) {
      //nlp_debug_msg('$voterIndex',$voterIndex);
      $page = (int) floor($voterIndex/$this::DE_PAGE_SIZE);
      //nlp_debug_msg('$page',$page);
    } else {
      $messenger = Drupal::messenger();
      $messenger->addStatus('Voter not found.');
    }
    //nlp_debug_msg('$voters',$voters);
    return ['found'=> $found,'page'=> $page];
  }
  
  /** * * * * * * * * * * * * * * * * * * * * * * * * * * * * * *
   * initializeDataReporting
   *
   * @param $form_state
   * @return bool
   */
  function initializeDataReporting($form_state): bool
  {
    $messenger = Drupal::messenger();
  
    // Get the session data.
    $tempSessionData = $this->privateTempstoreObj->get('nlpservices.session_data');
    $defaultVoterContactMethod = $tempSessionData->get('defaultVoterContactMethod');
    $form_state->set('defaultVoterContactMethod',$defaultVoterContactMethod);
  
    $sessionData = $this->sessionDataObj->getUserSession();
    if(empty($sessionData['mcid'])) {
      $messenger->addWarning('The MCID is missing from your user login, contact your coordinator.');
      return FALSE;
    }
    //nlp_debug_msg('$sessionData',$sessionData);
    $mcid = $sessionData['mcid'];
    $form_state->set('mcid', $mcid);
    $form_state->set('sessionData',$sessionData);
  
    $county = $this->sessionDataObj->getCounty();
    $form_state->set('county',$county);
  
    $canvassDate = $tempSessionData->get('canvassDate');
    if(empty($canvassDate)) {
      $canvassDate = date('Y-m-d',time());  // Today.
    }
    $form_state->set('canvassDate',$canvassDate);
  
    // Verify we know this NL.
    $nlsInfo = $this->nls->getNlById($sessionData['mcid']);
    // Stop if we don't have this person in the database.
    if (empty($nlsInfo)) {
      $messenger->addWarning('You are not in the list of active Neighborhood Leaders, contact your coordinator.');
      return FALSE;
    }
    $form_state->set('nlsStatus', $this->nls->getNlsStatus($sessionData['mcid'],$county));
  
    //Do we have a turf?
    if(!empty($sessionData['turfIndex'] AND !empty($this->turfs->getTurf($sessionData['turfIndex'])))) {
      $turfIndex = $sessionData['turfIndex'];
    } else {
      $turfArray = $this->turfs->turfExists($mcid,$county);
      $form_state->set('turfArray', $turfArray);
      if (empty($turfArray)) {
        $messenger->addWarning("You do not have a turf assigned");
        return FALSE;
      }
      $turfIndex = $turfArray['turfIndex'];
      $sessionData['turfIndex'] = $turfIndex;
      $this->sessionDataObj->setUserSession($sessionData);
    }
    $form_state->set('turfIndex', $turfIndex);
    
    // What page are we on?
    $currentPage = $tempSessionData->get('currentPage');
    //nlp_debug_msg('$currentPage',$currentPage);
    try {
      $tempSessionData->set('currentPage', $currentPage);
    } catch (Drupal\Core\TempStore\TempStoreException $e) {
      nlp_debug_msg('Temp store save error',$e->getMessage());
    }
    try {
      $tempSessionData->set('changePage', FALSE);
    } catch (Drupal\Core\TempStore\TempStoreException $e) {
      nlp_debug_msg('Temp store save error',$e->getMessage());
    }
  
    // Set the date for this turf access.
    $date = date('Y-m-d');
    $nlsStatus = $this->nls->getNlsStatus($mcid,$county);
    $nlsStatus['loginDate'] = $date;
    $this->nls->setNlsStatus($nlsStatus);
    $user = $this->drupalUserObj->getCurrentUser();
    $uid = $user['uid'];
    $editUpdate = [
      'uid' => $uid,
      'turfAccess' => $date,
    ];
    $this->drupalUserObj->updateUser($editUpdate);
  
    // Get the API keys.
    $config = Drupal::config('nlpservices.configuration');
    $apiKeys = $config->get('nlpservices-api-keys');
    $committeeKey = $apiKeys[$county];
    $committeeKey['API Key'] = $this->nlpEncrypt->encrypt_decrypt('decrypt', $committeeKey['API Key']);
    $form_state->set('committeeKey',$committeeKey);
    $stateCommitteeKey = $apiKeys['State Committee'];
    $stateCommitteeKey['API Key'] = $this->nlpEncrypt->encrypt_decrypt('decrypt', $stateCommitteeKey['API Key']);
    $form_state->set('stateCommitteeKey',$stateCommitteeKey);
  
    // Get the cycle info.
    $electionDates = $config->get('nlpservices-election-configuration');
    $cycle = $electionDates['nlp_election_cycle'];
    $form_state->set('cycle',$cycle);
    $form_state->set('cycleName',$electionDates['nlp_cycle_name']);
    $cycleParts = explode('-',$cycle);
    $cycleYear = $cycleParts[0];
    $form_state->set('cycleYear',$cycleYear);
  
    // Get the state name.
    $countyNames = $config->get('nlpservices-county-names');
    $state = $countyNames['State'];
    $form_state->set('state',$state);
  
    // Get the codes for reporting an NLP Voter and NLP Hostile.
    $nlpHostile = $config->get('nlpservices_hostile_ac');
    $form_state->set('nlpHostile',$nlpHostile);
    $nlpVoter = $config->get('nlpservices_voter_ac');
    $form_state->set('nlpVoter',$nlpVoter);
  
    // Get the permitted canvass response codes and contact methods.
    $canvassResponseCodes = $config->get('nlpservices_canvass_response_codes');
    $form_state->set('canvassResponseCodes',$canvassResponseCodes);
    $contactMethods = array_keys($canvassResponseCodes);
    array_unshift($contactMethods, 'Select method');
    $form_state->set('contactMethods',$contactMethods);
  return TRUE;
  }
    
      /** * * * * * * * * * * * * * * * * * * * * * * * * * * * * * *
       * @param $unused
       * @param $form
       * @return mixed
       * @noinspection PhpUnusedParameterInspection
       * @noinspection PhpUnused
       */
  function row0Callback($form, $unused) {
    return $form['voters']['voterForm']["cell03-0-body"]['no_contact-0'];
  }

  /** * * * * * * * * * * * * * * * * * * * * * * * * * * * * * *
   * @param $unused
   * @param $form
   * @return mixed
   * @noinspection PhpUnusedParameterInspection
   * @noinspection PhpUnused
   */
  function row1Callback($form, $unused) {
    return $form['voters']['voterForm']["cell03-1-body"]['no_contact-1'];
  }

  /** * * * * * * * * * * * * * * * * * * * * * * * * * * * * * *
   * @param $unused
   * @param $form
   * @return mixed
   * @noinspection PhpUnusedParameterInspection
   * @noinspection PhpUnused
   */
  function row2Callback($form, $unused) {
    return $form['voters']['voterForm']["cell03-2-body"]['no_contact-2'];
  }

  /** * * * * * * * * * * * * * * * * * * * * * * * * * * * * * *
   * @param $unused
   * @param $form
   * @return mixed
   * @noinspection PhpUnusedParameterInspection
   * @noinspection PhpUnused
   */
  function row3Callback($form, $unused) {
    return $form['voters']['voterForm']["cell03-3-body"]['no_contact-3'];
  }

  /** * * * * * * * * * * * * * * * * * * * * * * * * * * * * * *
   * @param $unused
   * @param $form
   * @return mixed
   * @noinspection PhpUnusedParameterInspection
   * @noinspection PhpUnused
   */
  function row4Callback($form, $unused) {
    return $form['voters']['voterForm']["cell03-4-body"]['no_contact-4'];
  }

  /** * * * * * * * * * * * * * * * * * * * * * * * * * * * * * *
   * @param $unused
   * @param $form
   * @return mixed
   * @noinspection PhpUnusedParameterInspection
   * @noinspection PhpUnused
   */
  function row5Callback($form, $unused) {
    return $form['voters']['voterForm']["cell03-5-body"]['no_contact-5'];
  }

  /** * * * * * * * * * * * * * * * * * * * * * * * * * * * * * *
   * @param $unused
   * @param $form
   * @return mixed
   * @noinspection PhpUnusedParameterInspection
   * @noinspection PhpUnused
   */
  function row6Callback($form, $unused) {
    return $form['voters']['voterForm']["cell03-6-body"]['no_contact-6'];
  }

  /** * * * * * * * * * * * * * * * * * * * * * * * * * * * * * *
   * @param $unused
   * @param $form
   * @return mixed
   * @noinspection PhpUnusedParameterInspection
   * @noinspection PhpUnused
   */
  function row7Callback($form, $unused) {
    return $form['voters']['voterForm']["cell03-7-body"]['no_contact-7'];
  }

  /** * * * * * * * * * * * * * * * * * * * * * * * * * * * * * *
   * @param $unused
   * @param $form
   * @return mixed
   * @noinspection PhpUnusedParameterInspection
   * @noinspection PhpUnused
   */
  function row8Callback($form, $unused) {
    return $form['voters']['voterForm']["cell03-8-body"]['no_contact-8'];
  }

  /** * * * * * * * * * * * * * * * * * * * * * * * * * * * * * *
   * @param $unused
   * @param $form
   * @return mixed
   * @noinspection PhpUnusedParameterInspection
   * @noinspection PhpUnused
   */
  function row9Callback($form, $unused) {
    return $form['voters']['voterForm']["cell03-9-body"]['no_contact-9'];
  }
  
  /** @noinspection PhpUnusedParameterInspection
   * @noinspection PhpUnused
   */
  function formCallback($form, $unused) {
    return $form['voters']['voterForm'];
  }
  
}