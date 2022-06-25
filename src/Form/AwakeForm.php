<?php

namespace Drupal\nlpservices\Form;

//use Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException;
//use Drupal\Component\Plugin\Exception\PluginNotFoundException;
//use Drupal\Core\Entity\EntityStorageException;
use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Config\ConfigFactoryInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
//use Drupal\node\Entity\Node;


class AwakeForm extends ConfigFormBase {
  
  const SURVEY_RESPONSE = 'SurveyResponse';
  const ACTIVIST_CODE = 'ActivistCode';
  
  protected $drupalUser;
  protected $nlsApiObj;
  protected $nlpEncrypt;
  protected $awardsObj;


  public function __construct(ConfigFactoryInterface $config_factory, $drupalUser, $nlsApiObj, $nlpEncrypt, $awardsObj) {
    parent::__construct($config_factory);
    $this->drupalUser = $drupalUser;
    $this->nlsApiObj = $nlsApiObj;
    $this->nlpEncrypt = $nlpEncrypt;
    $this->awardsObj = $awardsObj;

  }
  
  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('config.factory'),
      $container->get('nlpservices.drupal_user'),
      //$container->get('nlpservices.roles_permissions'),
      $container->get('nlpservices.api_nls'),
      $container->get('nlpservices.encryption'),
      $container->get('nlpservices.awards'),

    );
  }
  
  /**
   * {@inheritdoc}
   */
  protected function getEditableConfigNames() {
    return ['nlpservices.configuration'];
  }
  
  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'awake_form';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
  
  
  
    $form['date_bar'] = [
      '#markup' => '<div class="date-bar">',
    ];
  
    $form['date_box'] = [
      '#markup' => '<div class="date-box-left">',
    ];
    //nlp_debug_msg('$defaultDate',$defaultDate);
  
    $form['canvass_date'] = 'nice';
    $form['date_box_end'] = [
      '#markup' => '</div>',
    ];
    $form['counts_box'] = [
      '#markup' => '<div class="counts-box-left">',
    ];
    //$form['voter_counts'] = $this->voterCounts($turfInfo['voterCount'],$turfInfo['votedCount']);
  
    
    
    $form_element['counts'] = [
      '#markup' => "  \n ".'<div class="no-white voter-counts">',
    ];
  
    $form_element['table-start'] = [
      '#markup' => '<table class="table" ><tbody>',
    ];
    $form_element['row-one'] = [
      '#markup' => '<tr class="counts-row"><td  class="counts-name" >Name</td><td class="counts-numbers" ></td>',
    ];
    $form_element['row-two'] = [
      '#markup' => '<tr class="counts-row"><td class="counts-name">Big Name</td><td class="counts-numbers">10 (5%)</td>',
    ];
    $form_element['row-three'] = [
      '#markup' => '<tr class="counts-row"><td class="counts-name">Nameit</td><td class="counts-numbers">2 (1%)</td>',
    ];
    $form_element['table-end'] = [
      '#markup' => '</tbody></table>',
    ];
    
   
    $form_element['counts-end'] = array (
      '#markup' => " \n   ".'</div>',
    );
    $form['voter_counts'] = $form_element;
    
    
    //$form['voter_counts'] = $this->voterCounts($turfInfo);
    $form['date_counts_end'] = [
      '#markup' => '</div>',
    ];
  
   
  
    $form['date_bar_end'] = [
      '#markup' => '</div><div class="end-big-box"></div>',
    ];
    

    $form['awards'] = [
      '#type' => 'file',
      '#title' => $this->t('reports file'),
      '#description' => $this->t('Simplified list.'),
    ];

    $things = ['something','nothing'];
    
    $form['something_select'] = [
      '#type' => 'select',
      '#title' => $this->t('Something to select'),
      '#description' => $this->t('One or the other.'),
      '#options' => $things,
    ];
    
    return parent::buildForm($form, $form_state);
  }
  
  public function validateForm(array &$form, FormStateInterface $form_state)
  {
    $values = $form_state->getValues();
    //nlp_debug_msg('$values',$values);
    //nlp_debug_msg('validate',time());
    
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
  
}
