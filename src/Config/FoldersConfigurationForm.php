<?php

namespace Drupal\nlpservices\Config;

use Drupal;
use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\nlpservices\NlpPaths;
use Drupal\nlpservices\NlpDocuments;

/**
 * @noinspection PhpUnused
 */
class FoldersConfigurationForm extends ConfigFormBase {
  
  protected NlpPaths $nlpPaths;
  protected NlpDocuments $nlpDocuments;
  
  public function __construct( $config_factory, $nlpPaths, $nlpDocuments) {
    parent::__construct($config_factory);
    $this->nlpPaths = $nlpPaths;
    $this->nlpDocuments = $nlpDocuments;
  }
  
  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('config.factory'),
      $container->get('nlpservices.paths'),
      $container->get('nlpservices.documents'),
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
    return 'folders_configuration_form';
  }
  
  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state): array
  {
    $form['counties_names'] = [
      '#type' => 'file',
      '#title' => $this->t('YAML file with county names'),
      '#description' => $this->t('Please provide a file of county names for your state.'),
    ];
    return parent::buildForm($form, $form_state);
  }
  
  public function validateForm(array &$form, FormStateInterface $form_state)
  {
    $file_name = $_FILES['files']['name']['counties_names'];
    $file_name_lc = strtolower($file_name);
    $file_name_parts = explode('.', $file_name_lc);
    $file_type_extension = end($file_name_parts);
    $allowed = array('yml','yaml');
    if (!in_array($file_type_extension, $allowed)) {
      $form_state->setErrorByName('counties_names',
        $this->t('The county names file must be a yml or yaml type.'));
    }
    parent::validateForm($form, $form_state);
  }
  
  
  /** * * * * * * * * * * * * * * * * * * * * * * * * * * * * * *
   * prepareDocuments
   *
   */
  function prepareDocuments()
  {
    $messenger = Drupal::messenger();
    //$documentPath = $this->nlpPaths->getPath('DOCS', NULL);
    $modulePath = Drupal::service('extension.list.module')->getPath(NLP_MODULE);
    $defaultDocuments = $this->nlpDocuments->nameList;
    $currentDocuments = $this->nlpDocuments->getDocuments();
    foreach ($currentDocuments as $currentDocument) {
      if(isset($defaultDocuments[$currentDocument['name']])) {
        unset($defaultDocuments[$currentDocument['name']]);
      }
    }
    $documentFiles = scandir($modulePath.'/docs/');
    if(empty($documentFiles)) {
      $messenger->addWarning( $this->t('Default documents are missing'));
      return;
    }
    $defaultFiles = array();
    foreach ($documentFiles as $documentFile) {
      if(!is_dir($modulePath.'/docs/'.$documentFile)) {
        $path_parts = pathinfo($documentFile);
        if($path_parts['extension']=='pdf' OR $path_parts['extension']=='docx') {
          $filename = $path_parts['filename'];
          $root_parts = explode('_',$filename);
          $defaultFiles[$root_parts[0]] = $filename;
        }
      }
    }
    
    foreach ($defaultDocuments as $name => $defaultDocument) {
      $defaultFilename = $defaultDocument['defaultFilename'];
      if(isset($defaultFiles[$defaultFilename])) {
        //$from = $modulePath.'/docs/'.$defaultFiles[$defaultFilename];
        //$to = $documentPath.$defaultFiles[$defaultFilename];
        //$copy_docx = copy($from.'.docx',$to.'.docx');
        //$copy_pdf = copy($from.'.pdf',$to.'.pdf');
        //$doc = ($copy_docx)? $defaultFiles[$defaultFilename].'.docx' : NULL;
        //$pdf = ($copy_pdf)? $defaultFiles[$defaultFilename].'.pdf' : NULL;
        $doc = $defaultFiles[$defaultFilename].'.docx';
        $pdf = $defaultFiles[$defaultFilename].'.pdf';
        $document = array(
          'name' => $name,
          'weight' => $defaultDocument['weight'],
          'docFileName' => $doc,
          'pdfFileName' => $pdf,
          'description' => $defaultDocument['description'],
        );
        $this->nlpDocuments->createDocument($document);
      }
    }
  }
  
  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $counties_names = $_FILES['files']['tmp_name']['counties_names'];
    $counties = (array) yaml_parse_file($counties_names);
    //nlp_debug_msg('$counties',$counties);
    
    $this->config('nlpservices.configuration')
      ->set('nlpservices-county-names', $counties)
      ->save();

    unset($counties['State']);
    ksort($counties);
  
    $this->nlpPaths->createDir('TEMP',NULL);
    //$this->nlpPaths->createDir('DOCS',NULL);
    $this->nlpPaths->createDir('NLP',NULL);
    $this->nlpPaths->createDir('TURF',NULL);
   
    $countyNames = array_keys($counties);
    $subFolders = array('INST','PDF','TURF');
    foreach ($countyNames as $countyName) {
      $this->nlpPaths->createDir('COUNTY',$countyName);
      foreach ($subFolders as $subFolder) {
        $this->nlpPaths->createDir($subFolder,$countyName);
      }
    }
    $this->prepareDocuments();
    $modulePath = Drupal::service('extension.list.module')->getPath(NLP_MODULE);
    $magicWordsFile = $modulePath.'/src/Config/magic_words.yml';
    $magicWords = yaml_parse_file($magicWordsFile);
    $this->config('nlpservices.configuration')
      ->set('nlpservices-magic-words', $magicWords)
      ->save();
    
    parent::submitForm($form, $form_state);
  }
}
