<?php

namespace Drupal\nlpservices\Config;

use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;
//use Drupal\Core\Config\ConfigFactoryInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\nlpservices\NlpEncryption;


class ApiConfigurationForm extends ConfigFormBase {
  
  protected NlpEncryption $nlpEncrypt;
  
  public function __construct($config_factory, $nlpEncrypt) {
    parent::__construct($config_factory);
    $this->nlpEncrypt = $nlpEncrypt;
  }
  
  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('config.factory'),
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
    return 'api_configuration_form';
  }
  
  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state): array
  {
    $form['api_keys'] = [
      '#type' => 'file',
      '#title' => $this->t('YAML file with API keys'),
      '#description' => $this->t('Please provide a file of API keys for the participating counties..'),
    ];
    return parent::buildForm($form, $form_state);
  }
  
  public function validateForm(array &$form, FormStateInterface $form_state)
  {
    $file_name = $_FILES['files']['name']['api_keys'];
    $file_name_lc = strtolower($file_name);
    $file_name_parts = explode('.', $file_name_lc);
    $file_type_extension = end($file_name_parts);
    $allowed = array('yml','yaml');
    if (!in_array($file_type_extension, $allowed)) {
      $form_state->setErrorByName('counties_names',
        $this->t('The API keys file must be a yml or yaml type.'));
    }
    parent::validateForm($form, $form_state);
  }
  
  
  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $apiKeysFile = $_FILES['files']['tmp_name']['api_keys'];
    $apiKeys = yaml_parse_file($apiKeysFile);

    foreach ($apiKeys  as $committee=>$committeeKey) {
      $apiKeys[$committee]['API Key'] = $this->nlpEncrypt->encrypt_decrypt('encrypt', $committeeKey['API Key']);
    }

    $this->config('nlpservices.configuration')
      ->set('nlpservices-api-keys', $apiKeys)
      ->save();
    parent::submitForm($form, $form_state);
  }
  
}
