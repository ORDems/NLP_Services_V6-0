<?php

namespace Drupal\nlpservices\Config;

use Drupal;
use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;
//use Drupal\Core\Config\ConfigFactoryInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\nlpservices\ApiActivistCodes;
use Drupal\nlpservices\NlpEncryption;

/**
 * @noinspection PhpUnused
 */
class ActivistCodeConfigurationForm extends ConfigFormBase {
  
  protected ApiActivistCodes $apiActivistCodes;
  protected NlpEncryption $nlpEncrypt;
  
  public function __construct( $config_factory, $apiActivistCodes, $nlpEncrypt) {
    parent::__construct($config_factory);
    $this->apiActivistCodes = $apiActivistCodes;
    $this->nlpEncrypt = $nlpEncrypt;
  }
  
  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('config.factory'),
      $container->get('nlpservices.activist_codes'),
      $container->get('nlpservices.encryption'),
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
    return 'activist_code_configuration_form';
  }

  /** * * * * * * * * * * * * * * * * * * * * * * * * * * * * * *
   * activistCodeSelect
   *
   * @param $request
   * @return array
   */
  function activistCodeSelect($request): array
  {
    $name = $request['name'];
    $activistCodeList = $request['activistCodeList'];
    $currentActivistCode = $request['currentActivistCode'];
    //nlp_debug_msg('$request',$request);
    $codeName = (empty($currentActivistCode['name']))?'Not yet chosen':
      'Name: '.$currentActivistCode['name'].', Type: '.$currentActivistCode['type'].
      ', Description: '.$currentActivistCode['description'];
    
    $form_element['activist'] = array(
      '#title' => '"'.$name.'" AC Select',
      '#prefix' => " \n".'<div style="width:750px;">'." \n",
      '#suffix' => " \n".'</div>'." \n",
      '#type' => 'fieldset',
    );
    $form_element['activist']['currentAC'] = array(
      '#markup' => '<p><b>The currently chosen Activist code for "'.$name.'" is:</b><br>'.$codeName."</p>",
    );
    if($currentActivistCode != 'Not chosen yet') {
      $form_element['activist'][$name.'RemoveAC'] = array(
        '#type' => 'checkbox',
        '#title' => t('Remove the currently chosen activist code'),
      );
    }
    $form_element['activist'][$name.'ActivistCode'] = array(
      '#type' => 'select',
      '#title' => '"'.$name.'" activist code selection',
      '#options' => $activistCodeList,
      '#description' => t('Select the activist code to be set when a voter is declared to be "'.$name.'"'),
    );
    return $form_element;
  }
  
  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state): array
  {
    $config = $this->config('nlpservices.configuration');
    $apiKeys = $config->get('nlpservices-api-keys');
    //nlp_debug_msg('$apiKeys',$apiKeys);
    $stateCommitteeKey = $apiKeys['State Committee'];
    $stateCommitteeKey['API Key'] = $this->nlpEncrypt->encrypt_decrypt('decrypt', $stateCommitteeKey['API Key']);
    //nlp_debug_msg('$stateCommitteeKey',$stateCommitteeKey);
    $activistCodes = $this->apiActivistCodes->getApiActivistCodes($stateCommitteeKey,0);
    $form_state->set('activistCodes',$activistCodes);
    //nlp_debug_msg('$activistCodes',$activistCodes);
    $currentNlpHostile = $currentNlpVoter = [];
    $currentNlpHostileCode = $config->get('nlpservices_hostile_ac');
    $currentNlpVoterCode = $config->get('nlpservices_voter_ac');
    $activistCodeList = $acArray = [];
    $activistCodeList[1] = 'Select an Activist Code';
    foreach ($activistCodes as $activistCode) {
      $activistCodeList[$activistCode['activistCodeId']] = 'name:"'.$activistCode['name'].
        '", type="'.$activistCode['type'].'"';
      $acArray[$activistCode['activistCodeId']] = $activistCode;
      if($activistCode['activistCodeId'] == $currentNlpVoterCode) {
        $currentNlpVoter = $activistCode;

      }
      if($activistCode['activistCodeId'] == $currentNlpHostileCode) {
        $currentNlpHostile = $activistCode;

      }
    }
    $form_state->set('activistCodes',$acArray);
    //nlp_debug_msg('$currentNlpHostileCode',$currentNlpHostileCode);
    
    $request['name'] = "NLPHostile";
    $request['activistCodeList'] = $activistCodeList;
    $request['currentActivistCode'] = $currentNlpHostile;
    $form['nlp_hostile'] = $this->activistCodeSelect($request);

    $request['name'] = "NLPVoter";
    $request['currentActivistCode'] = $currentNlpVoter;
    $form['nlp_voter'] = $this->activistCodeSelect($request);
    return parent::buildForm($form, $form_state);
  }
  
  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $messenger = Drupal::messenger();
    $values = $form_state->getValues();
    //nlp_debug_msg('$values',$values);
    $config = $this->config('nlpservices.configuration');
    
    if(!empty($values['NLPHostileRemoveAC'])) {
      $messenger->addStatus( $this->t('The current activist code is deselected.'));
      //$this->nlpConfig->setConfigurationRecord('NLPHostile', []);
      $config->set('nlpservices_hostile_ac',NULL)->save();
    }
  
    if($values['NLPHostileActivistCode'] > 1) {
      //$activistCode = $activistCodes[$values['NLPHostileActivistCode']];
      //$this->nlpConfig->setConfigurationRecord('NLPHostile', $activistCode);
      $config->set('nlpservices_hostile_ac',$values['NLPHostileActivistCode'])->save();
      //nlp_debug_msg('NLPHostileActivistCode',$values['NLPHostileActivistCode']);
    }
  
    if(!empty($values['NLPVoterRemoveAC'])) {
      $messenger->addStatus( $this->t('The current activist code is deselected.'));
      //$this->nlpConfig->setConfigurationRecord('NLPVoter', []);
      $config->set('nlpservices_voter_ac',NULL)->save();
    }
  
    if($values['NLPVoterActivistCode'] > 1) {
      //$activistCode = $activistCodes[$values['NLPVoterActivistCode']];
      //$this->nlpConfig->setConfigurationRecord('NLPVoter', $activistCode);
      $config->set('nlpservices_voter_ac',$values['NLPVoterActivistCode'])->save();
      //nlp_debug_msg('NLPVoterActivistCode',$values['NLPVoterActivistCode']);
    }
    
    parent::submitForm($form, $form_state);
  }
  
}
