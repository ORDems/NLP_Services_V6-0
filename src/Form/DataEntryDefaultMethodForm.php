<?php

namespace Drupal\nlpservices\Form;

use Drupal;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\TempStore\PrivateTempStoreFactory;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * @noinspection PhpUnused
 */
class DataEntryDefaultMethodForm extends FormBase
{
  
  protected PrivateTempStoreFactory $userSession;
  
  public function __construct($userSession)
  {
    $this->userSession = $userSession;
  }
  
  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container): DataEntryDefaultMethodForm
  {
    return new static(
      $container->get('tempstore.private'),
    );
  }
  
  /**
   * {@inheritdoc}
   */
  public function getFormId(): string
  {
    return 'nlpservices_default_method_form';
  }
  
  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state): array
  {
    $config = Drupal::config('nlpservices.configuration');
    $canvassResponseCodes = $config->get('nlpservices_canvass_response_codes');
    $form_state->set('canvassResponseCodes',$canvassResponseCodes);
    //nlp_debug_msg('$canvassResponseCodes',$canvassResponseCodes);
    $contactMethods = array_keys($canvassResponseCodes);
    array_unshift($contactMethods, 'Select method');
    $form_state->set('contactMethods',$contactMethods);
    //nlp_debug_msg('$contactMethods',$contactMethods);

    $sessionData = $this->userSession->get('nlpservices.session_data');
    $method = $sessionData->get('defaultVoterContactMethod');
    //nlp_debug_msg('$method',$method);

    $default = 0;
    if(!empty($method)) {
      $default = array_search($method,$contactMethods);
    }

    $form["contact_method"] = array(
      '#type' => 'select',
      '#options' => $contactMethods,
      '#default_value' => $default,
      '#title' => t('Select default method'),
      '#description' => t('Choose the most common contact method for your voter contacts.'),
      );
  
    $form['save'] = array(
      '#type' => 'submit',
      '#value' => 'Save',
    );
    
    return $form;
  
  }
  
  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state)
  {
    $messenger = Drupal::messenger();
    $values = $form_state->getValues();
    //nlp_debug_msg('$values',$values);
    $contactMethods = $form_state->get('contactMethods');
    $sessionData = $this->userSession->get('nlpservices.session_data');
    $method = $contactMethods[$values['contact_method']];
    //nlp_debug_msg('$method',$method);
    try {
      $sessionData->set('defaultVoterContactMethod', $method);
    } catch (Drupal\Core\TempStore\TempStoreException $e) {
      nlp_debug_msg('Temp store save error',$e->getMessage());
    }
    $messenger->addStatus('You selected the method: '.$method);
  }
}
