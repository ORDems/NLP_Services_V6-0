<?php

namespace Drupal\nlpservices\Form;

use Drupal;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\File\FileSystemInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\nlpservices\NlpInstructions;
use Drupal\nlpservices\NlpPaths;

/**
 * @noinspection PhpUnused
 */
class UploadInstructionsForm extends FormBase
{
  
  protected NlpInstructions $instructions;
  protected NlpPaths $paths;
  protected FileSystemInterface $fileSystem;
  
  public function __construct($instructions,$paths,$fileSystem)
  {
    $this->instructions = $instructions;
    $this->paths = $paths;
    $this->fileSystem = $fileSystem;
  }
  
  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container): UploadInstructionsForm
  {
    return new static(
      $container->get('nlpservices.instructions'),
      $container->get('nlpservices.paths'),
      $container->get('file_system'),
    );
  }
  
  /**
   * {@inheritdoc}
   */
  public function getFormId(): string
  {
    return 'nlpservices_upload_instructions_form';
  }
  
  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state): array
  {
    // Get the instruction file names for this county, canvass and postcard.
    $factory = Drupal::service('tempstore.private');
    $store = $factory->get('nlpservices.session_data');
    $county = $store->get('County');
    $form_state->set('county',$county);
    
    $current = $this->instructions->getInstructions($county);
    //nlp_debug_msg('$current',$current);
    $form_state->set('current',$current);
    // Create the form to display of all the NLs
    
    $form['legend'] = array (
      '#type' => 'markup',
      '#markup' => t('Current instruction file name: '.$current['canvass']['originalFileName'].
        '</br>Current postcard file name: '.$current['postcard']['originalFileName']),
    );
    $form['instruction_form'] = array(
      '#title' => t('Select the file(s) to upload'),
      '#type' => 'fieldset',
    );
    // Name the PDF with the canvass instructions.
    $form['instruction_form']['canvass'] = array(
      '#type' => 'file',
      '#title' => t('Canvass instructions'),
      '#description' => t('The canvass instruction file must be in PDF format'),
      '#size' => 75,
      
    );
    // Name of the PDF with the instructions for a postcard.
    $form['instruction_form']['card'] = array(
      '#type' => 'file',
      '#title' => t('Postcard instructions'),
      '#size' => 75,
      
      '#description' => t('The postcard instruction file must be in PDF format'),
    );
   
    // A submit button to update the NL recruitment goals.
    $form['submit'] = array(
      '#type' => 'submit',
      '#value' => 'Upload Instructions',
      '#description' => t('Upload the instructions file(s)'),
      '#suffix' => '</section>',
    );
    
    return $form;
  }
  
  /**
   * {@inheritdoc}
   */
  public function validateForm(array &$form, FormStateInterface $form_state)
  {
  
    $canvass = $_FILES['files']['name']['canvass'];
    if ($canvass != '') {
      $cName = strtolower($canvass);
      $cNameArray = explode('.', $cName);
      $cType = end($cNameArray);
      if ($cType != 'pdf') {
        $form_state->setErrorByName('canvass',
          $this->t('The canvass instructions must be a PDF.'));
        return;
      }
      $stringLength = strlen($canvass);
      if ($stringLength > 160) {
        $form_state->setErrorByName('canvass',
          $this->t('The canvass instructions file name is too long.'));
        //form_set_error('canvass', 'The canvass instructions file name is too long.');
        return;
      }
    }
  
    $postcard = $_FILES['files']['name']['card'];
    if ($postcard != '') {
      $pdf = strtolower($postcard);
      $pdfArray = explode('.', $pdf);
      $cType = end($pdfArray);
      if ($cType != 'pdf') {
        $form_state->setErrorByName('card',
          $this->t('The postcard instruction file must be a PDF.'));
        return;
      }
      $stringLength = strlen($postcard);
      if ($stringLength > 160) {
        $form_state->setErrorByName('card',
          $this->t('The postcard instructions file name is too long.'));
      }
    }
  }
  
  
  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state)
  {
    $county = $form_state->get('county');
    $current = $form_state->get('current');
  
    $canvass = $_FILES['files']['name']['canvass'];
    if (!empty($canvass)) {
      $canvassTemp = $_FILES['files']['tmp_name']['canvass'];
      $this->updateInstructionsFile($county,'canvass',$canvass,$canvassTemp,$current);
    }
  
    $postcard = $_FILES['files']['name']['card'];
    if (!empty($postcard)) {
      $postcardTemp = $_FILES['files']['tmp_name']['card'];
      $this->updateInstructionsFile($county,'postcard',$postcard,$postcardTemp,$current);
    }
    
  }
  
  
  /** * * * * * * * * * * * * * * * * * * * * * * * * * * * * * *
   * updateInstructionsFile
   *
   * Move the file with the instructions in PDF format and remember the name.
   * Delete the previous file if it exists.
   *
   * @param $county
   * @param $type - either NE_CANVASS or NE_POSTCARD.
   * @param $newFilename
   * @param $temp
   * @param $current
   */
  function updateInstructionsFile($county,$type,$newFilename,$temp,$current)
  {
    $filename = $type.'_'.'instructions'.'_'.time().'.pdf';
    // Move the temp to the permanent location with a generic county name.
    $path = $this->paths->getPath('INST',$county);
    $fullName = $path.$filename;
    if(!empty($current[$type]['fileName'])) {
      $currentName = $path.$current[$type]['fileName'];
      unlink($currentName);
    }
    //drupal_move_uploaded_file($temp, $fullName);
    $this->fileSystem->moveUploadedFile($temp, $fullName);
    
    $req = array(
      'county' => $county,
      'type' => $type,
      'fileName' => $filename,
      'originalFileName' => $newFilename,
      'title' => NULL,
      'blurb' => NULL,
    );
    $this->instructions->createInstructions($req);
  }

}
