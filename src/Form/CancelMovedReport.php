<?php

namespace Drupal\nlpservices\Form;

use Drupal;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\nlpservices\NlpVoters;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\nlpservices\NlpReports;
use Drupal\nlpservices\NlpSessionData;

/**
 * @noinspection PhpUnused
 */

class CancelMovedReport extends FormBase
{
  
  protected NlpVoters $votersObj;
  protected NlpReports $reportsObj;
  protected NlpSessionData $sessionObj;
  
  public function __construct($votersObj, $reportsObj, $sessionObj)
  {
    $this->votersObj = $votersObj;
    $this->reportsObj = $reportsObj;
    $this->sessionObj = $sessionObj;
  
  }
  
  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container): CancelMovedReport
  {
    return new static(
      $container->get('nlpservices.voters'),
      $container->get('nlpservices.reports'),
      $container->get('nlpservices.session_data'),

    );
  }
  
  /**
   * {@inheritdoc}
   */
  public function getFormId(): string
  {
    return 'nlpservices_cancel_moved_report_form';
  }
  
  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state): array
  {
    if(!empty($form_state->get('reenter'))) {
      $form_state->set('reenter',TRUE);
      $form_state->set('options',[]);
    }
  
    $options = $form_state->get('options');
  
    $form['description'] = [
      '#type' => 'item',
      '#title' => 'Find a voter.',
      '#prefix' => " \n".'<div>'." \n",
      '#suffix' => " \n".'</div>'." \n",
      '#markup' => 'Select either the VANID if you know it or select the first and/or last name for a search.
       The name fields are case insensitive and they my be fragments of the actual name.',
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
    
    if(!empty($options)) {
  
      $form['settings']['voter'] = [
        '#type' => 'radios',
        '#title' => 'Voter select',
        '#options' => $options,
        '#description' => 'Choose one.',
      ];
  
      $form['chooseVoter'] = [
        '#type' => 'submit',
        '#value' => 'Remove the moved report for this voter.',
        '#name' => 'chooseVoter',
      ];
    }
    
    return $form;
  }
  
  public function validateForm(array &$form, FormStateInterface $form_state)
  {
    //$messenger = Drupal::messenger();
    $values = $form_state->getValues();
  
    $triggeringElement = $form_state->getTriggeringElement();
    $elementClicked = $triggeringElement['#name'];
    switch ($elementClicked) {
      
      case 'search':
        if(empty($values['vanid']) AND empty($values['firstName']) AND empty($values['lastName']) ) {
          //$messenger->addWarning("You must specify something for a search criteria.");
          $form_state->setErrorByName('vanid');
          $form_state->setErrorByName('firstName');
          $form_state->setErrorByName('lastName','You must specify something for a search criteria.');
        }
        break;
      case 'chooseVoter':
        if(empty($values['voter'] )) {
          //$messenger->addWarning("You must choose a voter.");
          $form_state->setErrorByName('voter',"You must choose a voter.");
        }
        break;
    }
  }
  
  
  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state)
  {
    $form_state->setRebuild();
    $messenger = Drupal::messenger();
    //$messenger->addMessage('Submit called. ',TRUE);
    $values = $form_state->getValues();
    //nlp_debug_msg('$values',$values);
    $triggeringElement = $form_state->getTriggeringElement();
    $elementClicked = $triggeringElement['#name'];
    //nlp_debug_msg('$elementClicked ',$elementClicked);
  
    $firstName = $form_state->getValue('firstName');
    $lastName = $form_state->getValue('lastName');
    $needles = ['firstName'=>$firstName,'lastName'=>$lastName];
  
    //$sessionObj = Drupal::getContainer()->get('nlpservices.session_data');
    $county = $this->sessionObj->getCounty();
    //nlp_debug_msg('$county',$county);
    //$votersObj = Drupal::getContainer()->get('nlpservices.voters');
    //$reportsObj = Drupal::getContainer()->get('nlpservices.reports');
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
        //nlp_debug_msg('$vanid',$vanid);
        $turf = $this->votersObj->getVotersTurf($vanid,$cycle);
        //nlp_debug_msg('$turf',$turf);
        $turfIndex = $turf['turfIndex'];
      
        $voter = $this->votersObj->getVoterById($vanid,$turfIndex);
        //nlp_debug_msg('$voter',$voter);
        $voters[$voter['vanid']] = $voter;
        $action = 'options';
      
        break;
    
    
      case 'search':
        $voters = $this->votersObj->searchVoters($county, $needles);
        //nlp_debug_msg('$voters',$voters);
        if (empty($voters)) {
          $messenger->addWarning('No voters found.');
          $form_state->set('options',NULL);
          break;
        } elseif (count($voters) > 250) {
          $messenger->addWarning('The list of voters is too long.');
          $form_state->set('options',NULL);
          break;
        }
        $action = 'options';
        break;
    
    
      case 'chooseVoter':
        $vanid = $values['voter'];
        //nlp_debug_msg('$vanid',$vanid);
      
        $addresses = $this->votersObj->getVoterAddresses($vanid);
        //nlp_debug_msg('$addresses',$addresses);
      
        $moved = $addresses[0]['moved'];
      
        $request['vanid'] = $vanid;
        $request['type'] = 'contact';
        $request['value'] = 'Moved';
        $request['cycle'] = $cycle;
        //nlp_debug_msg('$request',$request);
        $report = $this->reportsObj->getReport($request);
        $existingRIndex = (!empty($report['reportIndex']))?$report['reportIndex']:NULL;  // Report exists.
        //nlp_debug_msg('$existingRIndex',$existingRIndex);
      
        if(empty($moved) AND empty($existingRIndex)) {
          $messenger->addWarning('There is no report that this voter has moved.');
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
      $turf = $this->votersObj->getVotersTurf($vanid,$cycle);
      //nlp_debug_msg('$turf',$turf);
    
      $turfIndex = $turf['turfIndex'];
      //$messenger->addMessage('turf index = '. $turfIndex);
      //$messenger->addMessage('report index = '. $existingRIndex);
    
      if(!empty($existingRIndex)) {
        $this->reportsObj->deletereport($existingRIndex);
      }
    
      if(!empty($turfIndex)) {
        $movedStatus = 0;   // This voter did not move,
        $this->votersObj->setMovedStatus($turfIndex,$vanid,$movedStatus);
      }
      $messenger->addMessage('The report that this voter moved has been removed.');
      
      //$form_state->unsetValue('lastName');
      $form_state->set('options',NULL);
      $form_state->setRebuild(FALSE);
    }
  }
  
 
  
}
