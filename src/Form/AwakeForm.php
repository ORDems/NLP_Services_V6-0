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
  
    $currentPage = 7;
    $pageCount = 8;
    
    
    if($pageCount < 7) {
      $page = 1;
      $elementCount = $pageCount;
      $less = $more = FALSE;
    } elseif ($currentPage < 7) {
      $page = 1;
      $elementCount = 6;
      $less = FALSE;
      $more = TRUE;
    } else {
      $page = 7;
      $elementCount = $pageCount-6;
      $less = TRUE;
      $more = FALSE;
    }
    
    $form['navigation_box'] = array (
      '#markup' => "  \n ".'<section class="nav_box no-white">',
    );
    
    
    
  
    $form['nav'] = array(
      '#type' => 'fieldset',
      '#prefix' => " \n".'<div class="nav_div">'." \n",
      '#suffix' => " \n".'</div>'." \n",
      '#attributes' => array(
        'style' => array('background-image: none; border:0; padding:0; margin:0; '),),
    );
  
    
    if($less) {
      $form['nav']['less'] = array(
        '#type' => 'submit',
        '#value' => '< Previous',
        '#name' => 'less',
        '#prefix' => " \n".'<div class="nav_number" >'." \n",
        '#suffix' => " \n".'</div>'." \n",
      );
      $form['nav']['dots'] = array(
        '#markup' => ' ... ',
        '#prefix' => " \n".'<div class="nav_number" >'." \n",
        '#suffix' => " \n".'</div>'." \n",
      );
    }
    
  
    for ($element=1; $element<=$elementCount; $element++) {
      //$pageName = $formPageName[$element];
      
      if($page == $currentPage) {
        $hoverMessage = "You are on page $page.";
        $pageClass = 'nav_current_number';
      } else {
        $hoverMessage = "Jump to page $page.";
        $pageClass = 'nav_number';
      }
      $form['nav']['pageSelect-'.$page] = [
        '#type' => 'submit',
        '#value' => $page,
        '#name' => 'pageSelect-'.$page,
        '#prefix' => " \n".'<div class="'.$pageClass.'" title="'.$hoverMessage.'">'." \n",
        '#suffix' => " \n".'</div>'." \n",
      ];
      $page++;
    }
    
    if($more) {
      $form['nav']['more'] = array(
        '#type' => 'submit',
        '#value' => 'Next >',
        '#name' => 'more',
        '#prefix' => " \n".'<div class="nav_number" >'." \n",
        '#suffix' => " \n".'</div>'." \n",
      );
    }
    
   
    $form['navigation_end'] = array (
      '#type' => 'markup',
      '#markup' => " \n   ".'</section>',
    );
    

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
