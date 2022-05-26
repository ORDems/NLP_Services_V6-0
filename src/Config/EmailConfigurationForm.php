<?php

namespace Drupal\nlpservices\Config;

use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * @noinspection PhpUnused
 */
class EmailConfigurationForm extends ConfigFormBase {
  public function __construct($config_factory) {
    parent::__construct($config_factory);
  }
  
  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('config.factory'),
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
    return 'email_configuration_form';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state): array
  {
    $config = $this->config('nlpservices.configuration');
    $emailConfiguration = $config->get('nlpservices-email-configuration');
    //nlp_debug_msg('$emailConfiguration',$emailConfiguration);
    
    if(empty($emailConfiguration)) {
      $emailConfiguration = array(
        'notifications' => array(
          'email' => '',
          'port' => '',
          'password' => '',
          'server' => '',
        ),
        'minivan' => array(
          'email' => '',
          'port' => '',
          'password' => '',
          'server' => '',
        ),
        'matchback' => array(
          'email' => '',
          'port' => '',
          'password' => '',
          'server' => '',
        ),
      );
    }
    
    $form_state->set('emailConfiguration',$emailConfiguration);
  
    $form['notificationsHr'] = array (
      '#markup' => '<p>&nbsp;</p><hr><p>&nbsp;</p>',
    );
  
    $form['nlp_notifications_incoming_server'] = array(
      '#type' => 'textfield',
      '#id' => 'notifications_incoming_server',
      '#title' => t('IMAP incoming server for notifications'),
      '#default_value' => $emailConfiguration['notifications']['server'],
      '#size' => 40,
      '#maxlength' => 120,
      '#description' => t("Incoming server for reading notifications emails.."),
    );
  
    $form['nlp_notifications_port'] = array(
      '#type' => 'textfield',
      '#id' => 'notifications_port',
      '#title' => t('IMAP port for reading notifications email.'),
      '#default_value' => $emailConfiguration['notifications']['port'],
      '#size' => 40,
      '#maxlength' => 120,
      '#description' => t("Port (typically 993)."),
    );
  
    $form['nlp_email'] = array(
      '#type' => 'textfield',
      '#id' => 'email',
      '#title' => t('From email address'),
      '#default_value' => $emailConfiguration['notifications']['email'],
      '#size' => 40,
      '#maxlength' => 60,
      '#description' => t("Sending mail address to be used in all messages sent by NLP Services."),
      '#required' => TRUE,
    );
  
    $form['nlp_email_password'] = array(
      '#type' => 'textfield',
      '#id' => 'from_email_password',
      '#title' => t('Password for NLP Services email'),
      '#default_value' => $emailConfiguration['notifications']['password'],
      '#size' => 20,
      '#maxlength' => 20,
      '#description' => t("Password for the sending mail address."),
    );
  
    // MiniVan email config.
  
    $form['minivanHr'] = array (
      '#markup' => '<p>&nbsp;</p><hr><p>&nbsp;</p>',
    );
  
    $form['nlp_minivan_incoming_server'] = array(
      '#type' => 'textfield',
      '#id' => 'minivan_incoming_server',
      '#title' => t('MiniVAN IMAP incoming server'),
      '#default_value' => $emailConfiguration['minivan']['server'],
      '#size' => 40,
      '#maxlength' => 120,
      '#description' => t("Incoming server for reading MiniVAN report emails.."),
    );
  
    $form['nlp_minivan_port'] = array(
      '#type' => 'textfield',
      '#id' => 'minivan_port',
      '#title' => t('MiniVAN IMAP port for reading email.'),
      '#default_value' => $emailConfiguration['minivan']['port'],
      '#size' => 40,
      '#maxlength' => 120,
      '#description' => t("Port (typically 993)."),
    );
  
    $form['nlp_minivan_email'] = array(
      '#type' => 'textfield',
      '#id' => 'minivan_email',
      '#title' => t('Email for MiniVAN reports email address'),
      '#default_value' => $emailConfiguration['minivan']['email'],
      '#size' => 40,
      '#maxlength' => 60,
      '#description' => t("Email for receiving reports for MiniVAN users."),
    );
  
    $form['nlp_minivan_email_password'] = array(
      '#type' => 'textfield',
      '#id' => 'minivan_email_password',
      '#title' => t('Password for MiniVAN email'),
      '#default_value' => $emailConfiguration['minivan']['password'],
      '#size' => 20,
      '#maxlength' => 20,
      '#description' => t("Sending mail address to be used in all messages sent by NLP Services."),
    );
  
  
    // Matchback email config.
  
    $form['matchbackHr'] = array (
      '#markup' => '<p>&nbsp;</p><hr><p>&nbsp;</p>',
    );
  
    $form['nlp_matchback_incoming_server'] = array(
      '#type' => 'textfield',
      '#id' => 'matchback_incoming_server',
      '#title' => t('IMAP incoming server for matchbacks'),
      '#default_value' => $emailConfiguration['matchback']['server'],
      '#size' => 40,
      '#maxlength' => 120,
      '#description' => t("Incoming server for reading matchback emails.."),
    );
  
    $form['nlp_matchback_port'] = array(
      '#type' => 'textfield',
      '#id' => 'matchback_port',
      '#title' => t('IMAP port for reading matchback email.'),
      '#default_value' => $emailConfiguration['matchback']['port'],
      '#size' => 40,
      '#maxlength' => 120,
      '#description' => t("Port (typically 993)."),
    );
  
    $form['nlp_matchback_email'] = array(
      '#type' => 'textfield',
      '#id' => 'matchback_email',
      '#title' => t('Email for matchback reports'),
      '#default_value' => $emailConfiguration['matchback']['email'],
      '#size' => 40,
      '#maxlength' => 60,
      '#description' => t("Email for receiving reports for matchbacks."),
    );
  
    $form['nlp_matchback_email_password'] = array(
      '#type' => 'textfield',
      '#id' => 'matchback_email_password',
      '#title' => t('Password for matchback email'),
      '#default_value' => $emailConfiguration['matchback']['password'],
      '#size' => 20,
      '#maxlength' => 20,
      '#description' => t("Password for the matchback email account."),
    );
    return parent::buildForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $values = $form_state->getValues();
    //nlp_debug_msg('$values',$values);
    
    $emailInfo['email'] = $values['nlp_email'];
    $emailInfo['port'] = $values['nlp_notifications_port'];
    $emailInfo['password'] = $values['nlp_email_password'];
    $emailInfo['server'] = $values['nlp_notifications_incoming_server'];
    $nlpEmails['notifications'] = $emailInfo;
  
    $emailInfo['email'] = $values['nlp_minivan_email'];
    $emailInfo['port'] = $values['nlp_minivan_port'];
    $emailInfo['password'] = $values['nlp_minivan_email_password'];
    $emailInfo['server'] = $values['nlp_minivan_incoming_server'];
    $nlpEmails['minivan'] = $emailInfo;
  
    $emailInfo['email'] = $values['nlp_matchback_email'];
    $emailInfo['port'] = $values['nlp_matchback_port'];
    $emailInfo['password'] = $values['nlp_matchback_email_password'];
    $emailInfo['server'] = $values['nlp_matchback_incoming_server'];
    $nlpEmails['matchback'] = $emailInfo;
    
    //nlp_debug_msg('$nlpEmails',$nlpEmails);
    $this->config('nlpservices.configuration')
      ->set('nlpservices-email-configuration', $nlpEmails)
      ->save();
    
    parent::submitForm($form, $form_state);
  }
  
}
