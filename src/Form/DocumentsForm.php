<?php

namespace Drupal\nlpservices\Form;

use Drupal;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\File\FileSystemInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\nlpservices\NlpDocuments;
use Drupal\nlpservices\DrupalUser;
use Drupal\nlpservices\NlpPaths;


/**
 * @noinspection PhpUnused
 */
class DocumentsForm extends FormBase
{
  
  protected NlpDocuments $documents;
  protected DrupalUser $drupalUser;
  protected NlpPaths $paths;
  protected FileSystemInterface $filesystem;
  
  
  public function __construct( $documents, $drupalUser, $paths,  $filesystem)
  {
    $this->documents = $documents;
    $this->drupalUser = $drupalUser;
    $this->paths = $paths;
    $this->filesystem = $filesystem;
  }
  
  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container): DocumentsForm
  {
    return new static(
      $container->get('nlpservices.documents'),
      $container->get('nlpservices.drupal_user'),
      $container->get('nlpservices.paths'),
      $container->get('file_system'),
    );
  }
  
  /**
   * {@inheritdoc}
   */
  public function getFormId(): string
  {
    return 'nlpservices_documents_form';
  }
  
  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state): array
  {
    if (empty($form_state->get('reenter'))) {
      $form_state->set('reenter', TRUE);
      $factory = Drupal::service('tempstore.private');
      $store = $factory->get('nlpservices.session_data');
      $county = $store->get('County');
      $form_state->set('county', $county);
    }
    
    $county = $form_state->get('county');
    $form['county-name'] = [
      '#markup' => "<h1>".$county." County</h1>",
    ];
  
    $adminUser = $this->drupalUser->isNlpAdminUser();
    /*
    $documents = $this->documents->getDocuments();
    if(!empty($documents)) {
      $form['documents'] = $this->buildDocumentDisplay($documents);
    }
    */
  
    if($adminUser) {
      $form['new_documents'] = array(
        '#title' => 'Upload a new set of documents',
        '#prefix' => " \n".'<div style="width:310px;">'." \n",
        '#suffix' => " \n".'</div>'." \n",
        '#type' => 'fieldset',
      );
      $documentList = $this->documents->displayList();
    
      $form['new_documents']['document_type'] = array(
        '#type' => 'select',
        '#title' => t('Select the document type to upload.'),
        '#options' => $documentList,
        '#required' => TRUE,
      );
    
      $form['new_documents']['new_docx'] = array(
        '#type' => 'file',
        '#title' => t('The editable document'),
        '#size' => 60,
        '#maxlength' => 160,
        '#description' => t("A document in docx or doc format."),
      );
    
      $form['new_documents']['new_pdf'] = array(
        '#type' => 'file',
        '#title' => t('The same document in PDF format'),
        '#size' => 60,
        '#maxlength' => 160,
        '#description' => t("The document in PDF format."),
      );
    
    
      $form['new_documents']['submit'] = array(
        '#type' => 'submit',
        '#name' => 'document_submit',
        '#value' => 'Submit the new document >>'
      );
    }
    return $form;
  }
  
  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state)
  {
    $triggering_element = $form_state->getTriggeringElement();
    //nlp_debug_msg('submit - $triggering_element',$triggering_element);
    $element_clicked = $triggering_element['#name'];
    //nlp_debug_msg('submit - $element_clicked',$element_clicked);
  
    if($element_clicked != 'document_submit') {
      return;
    }
    $documentType = $form_state->getValue('documentType');
    $documents = $this->documents->getDocuments();
    $newDocument = $form_state->get('new_document');
    $oldDocument = $documents[$documentType];
    $this->updateDocumentsFile($newDocument,$oldDocument);
  }

  /** * * * * * * * * * * * * * * * * * * * * * * * * * * * * * *
   * buildDocumentDisplay
   * 
   * @param $documents
   * @return array
   */
  /*
  function buildDocumentDisplay($documents): array
  {
    $docPath = $this->paths->getPath('DOCS',NULL);
    $form_element['document_form'] = array(
      '#title' => 'Available NLP Documents.  (Right click the link to download the document.)',
      '#type' => 'fieldset',
      '#prefix' => '<div style="width:720px;">',
      '#suffix' => '</div>',
    );
    
    $header = [
      'name' => $this->t('Name'),
      'description' => $this->t('Description'),
      'doc' => $this->t('Doc'),
      'pdf' => $this->t('PDF')
    ];
    
    $rows = [];
    foreach ($documents as $document) {
  
      if(!empty($document['docFileName'])) {
        $docxUri = $docPath . $document['docFileName'];
        //$docUrl = file_create_url($docxUri);
        $docUrl = Drupal::service('file_url_generator')->generateAbsoluteString($docxUri);
        $doc = '(<a href="'.$docUrl.'">'.$document['docFileName'].'</a>) ';
      } else {
        $doc = '-';
      }
  
      if(!empty($document['pdfFileName'])) {
        $pdfUri = $docPath . $document['pdfFileName'];
        //$pdfUrl = file_create_url($pdfUri);
        $pdfUrl = Drupal::service('file_url_generator')->generateAbsoluteString($pdfUri);
        $pdf = '(<a href="'.$pdfUrl.'">'.$document['pdfFileName'].'</a>) ';
      } else {
        $pdf = '-';
      }
      
      $row = [
        'name' => $this->t($document['name']),
        'description' => $this->t($document['description']),
        'doc' => $this->t($doc),
        'pdf' => $this->t($pdf),
      ];
      $rows[$document['name']] = $row;
    }
    //nlp_debug_msg('$rows',$rows);
  
    $form_element['document_form']['table'] = array(
      '#type' => 'table',
      '#header' => $header,
      '#rows' => $rows,
      '#empty' => $this
        ->t('No documents found.'),
    );
    return $form_element;
  }
  */

  /** * * * * * * * * * * * * * * * * * * * * * * * * * * * * * *
   * updateDocumentsFile
   *
   * Move the file with the instructions in PDF format and remember the name.
   * Delete the previous file if it exists.
   *
   * @param $newDocument
   * @param $oldDocument
   */
  function updateDocumentsFile($newDocument,$oldDocument) {
    $documentType = $newDocument['documentType'];
    $docx = $newDocument['docx']['new'];
    $docxTemp = $newDocument['docx']['tmp'];
    $pdf = $newDocument['pdf']['new'];
    $pdfTemp = $newDocument['pdf']['tmp'];
    $oldDocx = $oldDocument['docFileName'];
    $oldPdf = $oldDocument['pdfFileName'];
    
    $nameList = $this->documents->nameList[$documentType];
    
    // If we have a new document, delete the current one and save the new one.
    if (!empty($docx)) {
      // If a file already exists, delete it.
      if(!empty($oldDocx)) {
        // Delete the current file.
        $fullPath = $this->paths->getPath('DOCS',NULL).$oldDocx;
        $this->filesystem->unlink($fullPath);
      }
      // Move the temp to the permanent location.
      $fullName = $this->paths->getPath('DOCS',NULL).$docx;
      $this->filesystem->moveUploadedFile($docxTemp, $fullName);
    }
    
    if (!empty($pdf)) {
      // If a file already exists, delete it.
      if(!empty($oldPdf)) {
        // Delete the current file.
        $fullPath = $this->paths->getPath('DOCS',NULL).$oldPdf;
        $this->filesystem->unlink($fullPath);
      }
      // Move the temp to the permanent location.
      $fullName = $this->paths->getPath('DOCS',NULL).$pdf;
      $this->filesystem->moveUploadedFile($pdfTemp, $fullName);
    }
    
    $req = array(
      'name' => $documentType,
      'weight' => $nameList['weight'],
      'docFileName' => $docx,
      'pdfFileName' => $pdf,
      'description' => $nameList['description'],
    );
    $this->documents->createDocument($req);
  }
}




