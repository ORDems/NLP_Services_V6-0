<?php

namespace Drupal\nlpservices\Form;

use Drupal;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\nlpservices\NlpTurfDeliveryMessage;
use Drupal\nlpservices\DrupalUser;

/**
 * @noinspection PhpUnused
 */
class TurfDeliveryMessageForm extends FormBase
{
  protected ConfigFactoryInterface $config;
  protected NlpTurfDeliveryMessage $turfMsg;
  protected DrupalUser $drupalUser;
  
  public function __construct($config, $turfMsg, $drupalUser) {
    $this->config = $config;
    $this->turfMsg = $turfMsg;
    $this->drupalUser = $drupalUser;
  }
  
  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container): TurfDeliveryMessageForm
  {
    return new static(
      $container->get('config.factory'),
      $container->get('nlpservices.turf_delivery_message'),
      $container->get('nlpservices.drupal_user'),
    );
  }
  
  /**
   * {@inheritdoc}
   */
  public function getFormId(): string
  {
    return 'nlpservices_turf_delivery_message_form';
  }
  
  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state): array
  {
    if (empty($form_state->get('reenter'))) {

      $factory = Drupal::service('tempstore.private');
      $store = $factory->get('nlpservices.session_data');
      $county = $store->get('County');
      $form_state->set('county',$county);
  
      $config = $this->config('nlpservices.configuration');
      
      $countyNames = $config->get('nlpservices-county-names');
      $form_state->set('countyNames',array_keys($countyNames));
      $form_state->set('state',$countyNames['State']);
  
      if($this->drupalUser->isNlpAdminUser()) {
        $form_state->set('page','county_select');
        $form_state->set('admin',TRUE);
      } else {
        $form_state->set('page','edit_county');
        $form_state->set('admin',FALSE);
        $form_state->set('county_selected',$county);
  
        $state = $form_state->get('state');
        $countyMsg = $this->turfMsg->getTurfMsg($state,$county);
        $form_state->set('countyMsg', $countyMsg);
      }
      
    }
    $county = $form_state->get('county');
    $state = $form_state->get('state');
    $countyNames = $form_state->get('countyNames');
    
    $page = $form_state->get('page');
    switch ($page) {
    
      case 'county_select':
        
        $form['county_select'] = array(
          '#type' => 'select',
          '#title' => t('State/County'),
          '#options' => $countyNames,
          '#description' => t('Select either the state or a specify county.'),
        );
        $form['county_select_submit'] = array(
          '#type' => 'submit',
          '#name' => 'county-submit',
          '#value' => t('Create the email message. >>'),
        );
        break;
    
      case 'edit_state':

        $form['state-name'] = [
          '#markup' => "<h1>Default email message for ".$state."</h1>",
        ];

        $form['body'] = array(
          '#type' => 'text_format',
          '#title' => $state.' Email Template',
          '#default_value' => $form_state->get('stateMsg'),
          '#format' => 'full_html',
          '#rows' => 25,
          '#description' => t('Template for the state email for turf delivery.'),
        );
      
        $form['state_edit'] = array(
          '#type' => 'submit',
          '#name' => 'state-edit',
          '#value' => t('Create or update the state email message. >>'),
        );
        
        break;
    
      case 'edit_county':

        $form['county-name'] = [
          '#markup' => "<h1>".$county." County</h1>",
        ];

        $countySelected = $form_state->get('county_selected');
        $form['body'] = array(
          '#type' => 'text_format',
          '#title' => $countySelected.' County Email Template',
          '#default_value' => $form_state->get('countyMsg'),
          '#format' => 'full_html',
          '#rows' => 25,
          '#description' => t('Template for the county email for turf delivery.'),
        );
      
        $form['county_edit'] = array(
          '#type' => 'submit',
          '#name' => 'county-edit',
          '#value' => t('Create or update the '.$countySelected.' County email message. >>'),
        );
        
        break;
    }
    
    return $form;
  }
  
  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state)
  {
    $form_state->set('reenter', TRUE);
    $form_state->setRebuild();
    $messenger = Drupal::messenger();
  
    $state = $form_state->get('state');
    $countyNames = $form_state->get('countyNames');
    
    $page = $form_state->get('page');
    switch ($page) {
    
      case 'county_select':
        $countySelect = $form_state->getValue('county_select');
        $countySelected = $countyNames[$countySelect];
       
        if($countySelected == 'State') {
          $stateMsg = $this->turfMsg->getTurfMsg($state,NULL);
        
          $form_state->set('stateMsg', $stateMsg);
          $form_state->set('page', 'edit_state');
        } else {
          $countyMsg = $this->turfMsg->getTurfMsg($state,$countySelected);
          $form_state->set('county_selected', $countySelected);
          $form_state->set('countyMsg', $countyMsg);
          $form_state->set('page', 'edit_county');
        }
        break;
    
      case 'edit_state':
        $stateMsg = $form_state->getValue('body')['value'];
        $tagExists = strpos($stateMsg,'@coordinatorContactInfo');
        if(!$tagExists) {
          $messenger->addError('The @coordinatorContactInfo tag is missing.  This tag must be somewhere
          in the body of the email message. ');
          return;
        }
        $this->turfMsg->putTurfMsg(NULL,$stateMsg);
        $form_state->set('page', 'county_select');
        break;
    
      case 'edit_county':
        $countySelected = $form_state->get('county_selected');
        $countyMsg = $form_state->getValue('body')['value'];
        $tagExists = strpos($countyMsg,'@coordinatorContactInfo');
        if(!$tagExists) {
          $messenger->addError('The @coordinatorContactInfo tag is missing.  This tag must be somewhere
          in the body of the email message. ');
          return;
        }
        $this->turfMsg->putTurfMsg($countySelected,$countyMsg);
        if($form_state->get('admin')) {
          $form_state->set('page', 'county_select');
        } else {
          $form_state->set('page', 'edit_county');
        }
        break;
    }
  }
  
}