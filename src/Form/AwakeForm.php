<?php

namespace Drupal\nlpservices\Form;

//use Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException;
//use Drupal\Component\Plugin\Exception\PluginNotFoundException;
//use Drupal\Core\Entity\EntityStorageException;
use Drupal;
use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Config\ConfigFactoryInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\Core\File\FileSystemInterface;

//use Drupal\node\Entity\Node;


class AwakeForm extends ConfigFormBase {
  
  const SURVEY_RESPONSE = 'SurveyResponse';
  const ACTIVIST_CODE = 'ActivistCode';
  
  protected $drupalUser;
  protected $nlsApiObj;
  protected $nlpEncrypt;
  protected $awardsObj;
  protected FileSystemInterface $filesObj;


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
  
    //$this->filesObj = Drupal::getContainer()->get('file_system');
    
    $nlpFilesDir = 'public://nlp_files';
  
    $counties = $this->getDirContent($nlpFilesDir);
    nlp_debug_msg('$counties',$counties);
    
    foreach ($counties['directories'] as $countyDir) {
      $countyDir = $nlpFilesDir.'/'.$countyDir;
      nlp_debug_msg('$countyDir',$countyDir);
      $countyDirContent = $this->getDirContent($countyDir);
      nlp_debug_msg('$countyDirContent',$countyDirContent);
  
      foreach ($countyDirContent['files'] as $countyFile) {
        $fileName = $countyDir.'/'.$countyFile;
        nlp_debug_msg('$fileName',$fileName);
        if(file_exists($fileName)) {
          nlp_debug_msg('file exists',$fileName);
        }
      }
      
      foreach ($countyDirContent['directories'] as $countyContentDir) {
        $countyContentDir = $countyDir.'/'.$countyContentDir;
        nlp_debug_msg('$countyContentDir',$countyContentDir);
        $countyContentDirContent = $this->getDirContent($countyContentDir);
        nlp_debug_msg('$countyContentDirContent',$countyContentDirContent);
        
        foreach ($countyContentDirContent['files'] as $countyFile) {
          $fileName = $countyContentDir.'/'.$countyFile;
          nlp_debug_msg('$fileName',$fileName);
  
          if(file_exists($fileName)) {
            nlp_debug_msg('file exists',$fileName);
          }
        }
      }
      
    }
    
    
    //$files = $this->filesObj->scanDirectory($nlpFilesDir,'');
    //nlp_debug_msg('$files',$files);
  
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
  
  
  function getDirContent($dir): array
  {
    $results = ['files'=>[],'directories'=>[]];
    if (!is_dir($dir)){
      return $results;
    }
  
    if ($dh = opendir($dir)) {
      while (($file = readdir($dh)) !== false) {
        if ($file != '.' and $file != '..') {
          if (is_dir($dir.'/'.$file)) {
            $results['directories'][] = $file;
          } else {
            $results['files'][] = $file;
          }
        }
      }
      closedir($dh);
    }
    return $results;
  }
  
}
