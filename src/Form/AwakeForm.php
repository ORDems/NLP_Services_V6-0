<?php /** @noinspection PhpUnused */

namespace Drupal\nlpservices\Form;

use Drupal;
use Drupal\Core\File\FileSystemInterface;
use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\nlpservices\NlpReplies;
use Symfony\Component\DependencyInjection\ContainerInterface;

class AwakeForm extends ConfigFormBase {
  
  protected NlpReplies $config_factory;
  
  protected NlpReplies $repliesObj;
  
  
  public function __construct(ConfigFactoryInterface $config_factory, $repliesObj) {
    $this->repliesObj = $repliesObj;
  
    parent::__construct($config_factory);
    $this->repliesObj = $repliesObj;
  
  }
  
  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    /** @noinspection PhpParamsInspection */
    return new static(
      $container->get('config.factory'),
      $container->get('nlpservices.email_replies'),

    );
  }
  
  /**
   * {@inheritdoc}
   */
  protected function getEditableConfigNames(): array
  {
    return ['nlpservices.configuration'];
  }
  
  /**
   * {@inheritdoc}
   */
  public function getFormId(): string
  {
    return 'awake_form';
  }
  
  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state): array
  {
    $messenger = Drupal::messenger();
  
    if(!empty($form_state->get('reenter'))) {
      $form_state->set('reenter',TRUE);
      $form_state->set('options',[]);
    }
  
    $options = $form_state->get('options');
    /*
    $nlpEncrypt = Drupal::getContainer()->get('nlpservices.encryption');
  
    $config = $this->config('nlpservices.configuration');
    $apiKeys = $config->get('nlpservices-api-keys');
    //$committeeKey = $apiKeys['State Committee'];
    //$committeeKey['API Key'] = $nlpEncrypt->encrypt_decrypt('decrypt', $committeeKey['API Key']);
  
    $apiNls = Drupal::getContainer()->get('nlpservices.api_nls');
    $mcid = 101590467;
  
    foreach ($apiKeys as $committee=>$committeeKey) {
      //nlp_debug_msg('$committee',$committee);
      $committeeKey['API Key'] = $nlpEncrypt->encrypt_decrypt('decrypt', $committeeKey['API Key']);
      //nlp_debug_msg('$committeeKey',$committeeKey);
      
      $nlRecord = $apiNls->getApiNls($committeeKey,$mcid,TRUE);
      if(!empty($nlRecord[0]) AND $nlRecord[0]=='Invalid key') {
        $messenger->addMessage('Invalid Key for '.$committee);
      } elseif (!empty($nlRecord['mcid']) AND $nlRecord['mcid']!=$mcid) {
        nlp_debug_msg('$nlRecord',$nlRecord);
      }
      
    }
    
    
    
    
    
    $mcid = 101590467;
    */
  
    //$mcid = 100743936;
  
  
    //$nlRecord = $apiNls->getApiNls($committeeKey,$mcid);
    //nlp_debug_msg('$nlRecord',$nlRecord);
  
    $form['description'] = [
      '#type' => 'item',
      '#title' => 'Find a voter.',
      '#prefix' => " \n".'<div>'." \n",
      '#suffix' => " \n".'</div>'." \n",
      '#markup' => 'Select either the VANID if you know it or select the first and/or last name for a search.  ',
    ];
  
    $form['findVoter'] = [
      '#title' => 'Enter search criteria',
      '#prefix' => " \n".'<div id="add-fix" style="width:400px;">'." \n",
      '#suffix' => " \n".'</div>'." \n",
      '#type' => 'fieldset',
    ];
   
    // VANID data entry field.
    $form['findVoter']['vanid'] = [
      '#title' => 'VANID in VoterFile',
      '#size' => 11,
      '#type' => 'textfield',
    ];
    // Voter's first name.
    $form['findVoter']['firstName'] = [
      '#title' => 'First Name',
      '#size' => 40,
      '#type' => 'textfield',
    ];
    // Last name data entry field.
    $form['findVoter']['lastName'] = [
      '#title' => 'Last Name',
      '#size' => 40,
      '#type' => 'textfield',
    ];
    
    // Add a submit button.
    $form['findVoter']['voterSearch'] = [
      '#type' => 'submit',
      '#value' => 'Find a voter.',
      '#name' => 'search',
    ];
    
    
    if(empty($options)) {
      return parent::buildForm($form, $form_state);
    }
  
    $form['settings']['voter'] = [
      '#type' => 'radios',
      '#title' => 'Voter select',
      '#options' => $options,
      '#description' => 'Choose one.',
    ];
  
    $form['chooseVoter'] = [
      '#type' => 'submit',
      '#value' => 'Do something.',
      '#name' => 'chooseVoter',
    ];
    
    return parent::buildForm($form, $form_state);
  }
  
  public function validateForm(array &$form, FormStateInterface $form_state)
  {
    //$messenger = Drupal::messenger();
    //$messenger->addMessage('Verify called. ');
    //$values = $form_state->getValues();
    //nlp_debug_msg('$values',$values);
    parent::validateForm($form, $form_state);
  }
  
  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $form_state->setRebuild();
    $messenger = Drupal::messenger();
    $messenger->addMessage('Submit called. ',TRUE);
    $values = $form_state->getValues();
    nlp_debug_msg('$values',$values);
    $triggeringElement = $form_state->getTriggeringElement();
    $elementClicked = $triggeringElement['#name'];
    nlp_debug_msg('$elementClicked ',$elementClicked);
  
    $firstName = $form_state->getValue('firstName');
    $lastName = $form_state->getValue('lastName');
    $needles = ['firstName'=>$firstName,'lastName'=>$lastName];
  
    $sessionObj = Drupal::getContainer()->get('nlpservices.session_data');
    $county = $sessionObj->getCounty();
  
    $votersObj = Drupal::getContainer()->get('nlpservices.voters');
    $reportsObj = Drupal::getContainer()->get('nlpservices.reports');
    //$turfsObj = Drupal::getContainer()->get('nlpservices.turfs');
  
    $config = Drupal::config('nlpservices.configuration');
    $electionDates = $config->get('nlpservices-election-configuration');
    $cycle = $electionDates['nlp_election_cycle'];
    //nlp_debug_msg('$cycle',$cycle);
  
    $vanid = $values['vanid'];
  
    if($elementClicked=='search' AND !empty($vanid)) {$elementClicked='vanid';}
    $action = 'nada';
    $existingRIndex = 0;
    $voters = [];
    switch ($elementClicked) {
      case 'vanid':
        //$vanid = $form_state->getValue('vanid');
        nlp_debug_msg('$vanid',$vanid);
        $turf = $votersObj->getVotersTurf($vanid,$cycle);
        nlp_debug_msg('$turf',$turf);
        $turfIndex = $turf['turfIndex'];
  
        $voter = $votersObj->getVoterById($vanid,$turfIndex);
        nlp_debug_msg('$voter',$voter);
        $voters[$voter['vanid']] = $voter;
        $action = 'options';
  
        break;
        
        
      case 'search':
        $voters = $votersObj->searchVoters($county, $needles);
        //nlp_debug_msg('$voters',$voters);
        if (empty($voters)) {
          $messenger->addMessage('No voters found.');
          break;
        }
        $action = 'options';
        break;
        
        
      case 'chooseVoter':
        $vanid = $values['voter'];
        //nlp_debug_msg('$vanid',$vanid);
  
        $addresses = $votersObj->getVoterAddresses($vanid);
        //nlp_debug_msg('$addresses',$addresses);
  
        $moved = $addresses[0]['moved'];
  
        $request['vanid'] = $vanid;
        $request['type'] = 'contact';
        $request['value'] = 'Moved';
        $request['cycle'] = $cycle;
        //nlp_debug_msg('$request',$request);
        $report = $reportsObj->getReport($request);
        $existingRIndex = (!empty($report['reportIndex']))?$report['reportIndex']:NULL;  // Report exists.
        //nlp_debug_msg('$existingRIndex',$existingRIndex);
  
        if(empty($moved) AND empty($existingRIndex)) {
          $messenger->addMessage('There is no report that this voter has moved.');
          //$form_state->set('options',NULL);
          $form_state->set('options',NULL);
          $form_state->unsetValue('lastName');
          break;
        }
  
        $messenger->addMessage('This voter has been reported as having moved.');
        $action = 'done';
        break;
    }
    
    if($action=='options') {
      $options = [];
      foreach ($voters as $vanid => $voter) {
    
        //$addresses = $votersObj->getVoterAddresses($vanid);
        //nlp_debug_msg('$addresses',$addresses);
        //$county = $addresses[0][$vanid]['county'];
        $option = $voter['lastName'] . ',' . $voter['firstName'] . ' ';
        if (!empty($voter['nickname']) and $voter['nickname'] != $voter['firstName']) {
          $option .= '(' . $voter['nickname'] . ') ';
        }
        $option .= $voter['age'] . ' ' . $voter['sex'] . ' ' . $voter['party'] . ' ';
        if (!empty($voter['homePhone'])) {
          $option .= 'H ' . $voter['homePhone'] . ' ';
        }
        if (!empty($voter['cellPhone'])) {
          $option .= 'C ' . $voter['cellPhone'] . ' ';
        }
        //$option .= $voter['county'] . ' [' . $vanid . ']';
        $option .= ' [' . $vanid . ']';
  
        $options[$vanid] = $option;
      }
      //nlp_debug_msg('$options',$options);
  
      $form_state->set('options', $options);
    } elseif ($action=='done') {
      $turf = $votersObj->getVotersTurf($vanid,$cycle);
      //nlp_debug_msg('$turf',$turf);
  
      $turfIndex = $turf['turfIndex'];
      $messenger->addMessage('turf index = '. $turfIndex);
      $messenger->addMessage('report index = '. $existingRIndex);
  
      if(!empty($existingRIndex)) {
        $reportsObj->deletereport($existingRIndex);
      }
  
      if(!empty($turfIndex)) {
        $movedStatus = 0;   // This voter did not move,
        $votersObj->setMovedStatus($turfIndex,$vanid,$movedStatus);
      }
      $messenger->addMessage('The report that this voter moved has been removed.');
  
  
      //$form_state->unsetValue('lastName');
      $form_state->set('options',NULL);
      $form_state->setRebuild(FALSE);
    }
    
    parent::submitForm($form, $form_state);
  }
  
  
  
}
