<?php

namespace Drupal\nlpservices\Form;

use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Config\ConfigFactoryInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

class AwakeForm extends ConfigFormBase {
  
  public function __construct(ConfigFactoryInterface $config_factory) {
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
    return 'awake_form';
  }
  
  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state): array
  {
    if(!$form_state->get('reenter')) {
      $form_state->set('reenter',TRUE);
      $default = 'select';
      $voterMethod = [
        'voter1Method' => 'select',
        'voter2Method' => 'select',
      ];
      $method = [
        'methods' => [
          'select'=>'Select something',
          'phone'=>'Phone',
          'walk'=>'Walk',
          'text'=>'Text'],
        'responses' => [
          'select'=>['Select a method first'],
          'phone'=>['Left Message','Refused'],
          'walk'=>['Left lit','Not home'],
          'text'=>['Sent text','Do  not text'],
        ],
        'defaultMethod' => $default,
        'voterMethod' => $voterMethod,
      ];
      $form_state->set('method',$method);
    }
    
    $method = $form_state->get('method');
    nlp_debug_msg('$method',$method);
    $form['defaultMethod'] = $this->defaultMethod($method);
    $form['voters'] = $this->buildTable($method);
    //nlp_debug_msg('$form',$form);
    return parent::buildForm($form, $form_state);
  }
  
  public function validateForm(array &$form, FormStateInterface $form_state)
  {
    $values = $form_state->getValues();
    nlp_debug_msg('$values',$values);
    
    $triggeringElement = $form_state->getTriggeringElement();
    $elementClicked = $triggeringElement['#name'];
    //nlp_debug_msg('$elementClicked ',$elementClicked);
    $method = $form_state->get('method');
    
    switch ($elementClicked) {
      case 'defaultMethod':
        $default = $values['defaultMethod'];
        $method['defaultMethod'] = $default;
        $method['voterMethod'] = [
          'voter1Method' => $default,
          'voter2Method' => $default,
        ];
        $form_state->setRebuild();
        break;
      case 'voter1Method':
      case 'voter2Method':
        $method['voterMethod'][$elementClicked] = $values[$elementClicked];
        break;
    }
    $form_state->set('method',$method);
    parent::validateForm($form, $form_state);
  }
  
  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $values = $form_state->getValues();
    nlp_debug_msg('$values',$values);
    $triggeringElement = $form_state->getTriggeringElement();
    $elementClicked = $triggeringElement['#name'];
    nlp_debug_msg('$elementClicked ',$elementClicked);
    parent::submitForm($form, $form_state);
  }
  
  function buildTable($method): array
  {
    $form_element['voterForm_fieldset_container'] = [
      '#type' => 'container',
      '#attributes' => ['id' => 'voterForm-fieldset-container'],
    ];
    $form_element['voterForm_fieldset_container']['voterForm_fieldset'] = [
      '#type' => 'fieldset',
      '#title' => $this->t('Choose something'),
    ];
  
    $defaultMethod = $method['defaultMethod'];
    $title = t('Select the method for voter 1');
    $options1 = $method['methods'];
    if($method['defaultMethod'] != 'select') {
      $form_element['voterForm_fieldset_container']['voterForm_fieldset']['showDefault'] = [
        '#markup' => "<b>The chosen method is ".$method['methods'][$defaultMethod]."</b>",
      ];
      $options1['select'] = 'Choose something else';
      $title = t('Or, choose a different method');
    }
    $voter1Method = $method['voterMethod']['voter1Method'];
    $form_element['voterForm_fieldset_container']['voterForm_fieldset']['voter1Method'] = [
      '#type' => 'select',
      '#options' => $options1,
      '#title' => $title,
      '#default_value' => $voter1Method,
      '#ajax' => [
        'callback' => '::formCallback',
        'wrapper' => 'voterForm-fieldset-container',
      ],
    ];
    $form_element['voterForm_fieldset_container']['voterForm_fieldset']['voter1Response'] = [
      '#type' => 'select',
      '#options' => $method['responses'][$voter1Method],
      '#title' => t('Select the response for voter 1'),
    ];
  
    $options2 = $method['methods'];
    if($defaultMethod != 'select') {
      unset($options2[$defaultMethod]);
      $options2 = array_merge(["$defaultMethod" => $method['methods'][$defaultMethod]], $options2);
    }
    
    nlp_debug_msg('$options2',$options2);
    
    $voter2Method = $method['voterMethod']['voter2Method'];
    $form_element['voterForm_fieldset_container']['voterForm_fieldset']["voter2Method"] = [
      '#type' => 'select',
      '#options' => $options2,
      '#title' => t('Select the method for voter 2'),
      '#default_value' => $voter2Method,
      '#ajax' => [
        'callback' => '::formCallback',
        'wrapper' => 'voterForm-fieldset-container',
      ],
    ];
    $form_element['voterForm_fieldset_container']['voterForm_fieldset']['voter2Response'] = [
      '#type' => 'select',
      '#options' => $method['responses'][$voter2Method],
      '#title' => t('Select the response for voter 2'),
    ];
    return $form_element;
  }
  
  function defaultMethod ($methods): array
  {
    $form_element['defaultMethod'] = [
      '#type' => 'select',
      '#options' => $methods['methods'],
      '#default_value' => $methods['defaultMethod'],
      '#title' => t('Select default method'),
      '#ajax' => [
        'callback' => '::formCallback',
        'wrapper' => 'voterForm-fieldset-container',
      ],
    ];
    return $form_element;
  }
  
  /** @noinspection PhpUnusedParameterInspection
   * @noinspection PhpUnused
   */
  function formCallback($form, $unused) {
    return $form['voters']['voterForm_fieldset_container'];
  }
  
}
