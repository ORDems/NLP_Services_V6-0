<?php

namespace Drupal\nlpservices\Config;

use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;
//use Drupal\Core\Config\ConfigFactoryInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\nlpservices\ApiResponseCodes;
use Drupal\nlpservices\NlpEncryption;


/**
 * @noinspection PhpUnused
 */
class ResponseCodeConfigurationForm extends ConfigFormBase {
  
  protected ApiResponseCodes $apiResponseCode;
  protected NlpEncryption $nlpEncrypt;
  
  public function __construct( $config_factory, $apiResponseCode, $nlpEncrypt) {
    parent::__construct($config_factory);
    $this->apiResponseCode = $apiResponseCode;
    $this->nlpEncrypt = $nlpEncrypt;
  }
  
  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('config.factory'),
      $container->get('nlpservices.response_codes'),
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
    return 'canvass_response_code_configuration_form';
  }


  /** * * * * * * * * * * * * * * * * * * * * * * * * * * * * * *
   * buildCanvassCodeSelect
   *
   * Build a table of canvass response codes and identify the recommended
   * selections and the current selects that are active.
   *
   * @param $apiKnownResultCodes
   * @param $apiExpectedResultCodes
   * @param $currentResponseCodes
   * @param $codes
   * @return array
   */
  function buildCanvassCodeSelect($apiKnownResultCodes, $apiExpectedResultCodes,$currentResponseCodes,&$codes): array
  {
    if(!empty($apiKnownResultCodes)) {
      $responseCodes = array();
      foreach ($apiKnownResultCodes as $contentType => $typeResponseCodes) {
        $contentTypeId = $typeResponseCodes['code'];
        if(empty($typeResponseCodes['responses'])) {continue;}
        $typeResponses = $typeResponseCodes['responses'];
        foreach ($typeResponses as $responseName => $response) {
          $responseCodes[$contentTypeId]['name'] = $contentType;
          $responseCodes[$contentTypeId][$response] = $responseName;
        }
      }
      $codes['responseCodes'] = $responseCodes;
      
      //nlp_debug_msg('$currentResponseCodes',$currentResponseCodes);
      $defaultCodes = array();
      if(!empty($currentResponseCodes)) {
        foreach ($currentResponseCodes as $contactType) {
          $contactTypeCode = $contactType['code'];
          foreach ($contactType['responses'] as $responseCode) {
            $defaultCodes[$contactTypeCode][$responseCode] = TRUE;
          }
        }
      }
      $codes['defaultCodes'] = $defaultCodes;
    }
    //nlp_debug_msg('$codes',$codes);
    
    $form_element['code_display'] = array(
      '#title' => 'Verification of canvass response codes',
      '#prefix' => " \n".'<div style="width:400px;">'." \n",
      '#suffix' => " \n".'</div>'." \n",
      '#type' => 'fieldset',
    );
    
    $header = [
      'recommended' => 'Recommended',
      'type' => 'Contact Type',
      'responses' => 'Responses',
      'code' => 'Code',
    ];
   
    $rows = $defaults = [];
    foreach ($apiKnownResultCodes as $contentType => $responseArray) {
      $recommendedResponses = array();
      if(!empty($apiExpectedResultCodes[$contentType])) {
        $recommendedResponses = $apiExpectedResultCodes[$contentType];
      }
      $contentTypeId = $responseArray['code'];
      if(empty($responseArray['responses'])) {continue;}
      foreach ($responseArray['responses'] as $responseName => $responseCodeId) {
        $recommended = '';
        if(!empty($recommendedResponses['responses'][$responseName])) {
          $recommended = '&#x2714;';
        }
        $row = [
          'recommended' => t($recommended),
          'type' => $contentType,
          'responses' => $responseName,
          'code' => $responseCodeId,
        ];
        $rowId = $contentTypeId.'-'.$responseCodeId;
        $rows[$rowId] = $row;
        if(!empty($defaultCodes[$contentTypeId][$responseCodeId])) {
          $defaults[$rowId] = TRUE;
        }
      }
    }
   
    $form_element['code_display']['table'] = [
      '#type' => 'tableselect',
      '#header' => $header,
      '#options' => $rows,
      '#default_value' => $defaults,
      '#js_select' => FALSE,
      '#empty' => $this
        ->t('No users found'),
      ];
    
    return $form_element;
  }
  
  
  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state): array
  {
  
    $config = $this->config('nlpservices.configuration');
    $apiKeys = $config->get('nlpservices-api-keys');
    $currentResponseCodes = $config->get('nlpservices_canvass_response_codes');
    //nlp_debug_msg('$currentResponseCodes',$currentResponseCodes);
    if(empty($currentResponseCodes)) {$currentResponseCodes=[];}
  
    $stateCommitteeKey = $apiKeys['State Committee'];
    $stateCommitteeKey['API Key'] = $this->nlpEncrypt->encrypt_decrypt('decrypt', $stateCommitteeKey['API Key']);
    //nlp_debug_msg('$stateCommitteeKey',$stateCommitteeKey);
    $apiKnownResponseCodes = $this->apiResponseCode->getApiKnownResultCodes($stateCommitteeKey,0);
    //nlp_debug_msg('$apiKnownResponseCodes',$apiKnownResponseCodes);
    $apiExpectedResultCodes = $this->apiResponseCode->getApiExpectedResultCodes();
    //nlp_debug_msg('$apiExpectedResultCodes',$apiExpectedResultCodes);
    $form['code_select'] = $this->buildCanvassCodeSelect(
      $apiKnownResponseCodes,$apiExpectedResultCodes,$currentResponseCodes,$codes);
    $form_state->set('codes',$codes);
  
    return parent::buildForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $values = $form_state->getValues();
    //nlp_debug_msg('$values',$values);
    $config = $this->config('nlpservices.configuration');
  
    $codes = $form_state->get('codes');
    //nlp_debug_msg('codes',$codes);
    $responseCodes = $codes['responseCodes'];
    $selectedCodes = [];
  
    foreach ($values['table'] as $value) {
      if(!empty($value)) {
        //nlp_debug_msg('$selection',$selection);
        $ids = explode('-',$value);
        //nlp_debug_msg('$ids',$ids);
  
        $contentTypeId = $ids[0];
        $responseId = $ids[1];
        $contentType = $responseCodes[$contentTypeId]['name'];
        $responseName = $responseCodes[$contentTypeId][$responseId];
        $selectedCodes[$contentType]['name'] = $contentType;
        $selectedCodes[$contentType]['code'] = $contentTypeId;
        $selectedCodes[$contentType]['responses'][$responseName] = $responseId;
      }
    }
    //nlp_debug_msg('$selectedCodes',$selectedCodes);
    $config->set('nlpservices_canvass_response_codes',$selectedCodes)->save();
    parent::submitForm($form, $form_state);
  }
}
