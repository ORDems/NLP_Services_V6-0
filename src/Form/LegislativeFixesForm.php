<?php

namespace Drupal\nlpservices\Form;

use Drupal;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\nlpservices\NlpLegislativeFixes;

/**
 * @noinspection PhpUnused
 */
class LegislativeFixesForm extends FormBase
{
  
  protected NlpLegislativeFixes $legFixes;
  
  public function __construct($legFixes)
  {
    $this->legFixes = $legFixes;
  }
  
  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container): LegislativeFixesForm
  {
    return new static(
      $container->get('nlpservices.legislative_fixes'),
    );
  }
  
  /**
   * {@inheritdoc}
   */
  public function getFormId(): string
  {
    return 'nlpservices_legislative_fixes_form';
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
    // Description.
    $form['fix_description'] = array(
      '#type' => 'item',
      '#title' => t('Substitute for missing HD and precinct'),
      '#prefix' => " \n".'<div>'." \n",
      '#suffix' => " \n".'</div>'." \n",
      '#markup' => 'Either select one or more fixes to remove (if present) or add a new one.  The substitute values 
      are used when a list of Prospective NLs is uploaded and the substitution is made only if the uploaded values 
      for HD/pct are empty.   After setting the substitute values, upload the list of prospective NLs again.',
    );
    $existingFixes = $this->legFixes->getLegFixes($county);
    // If fixes exist, display them in case one or more are to be deleted.
    if(!empty($existingFixes)) {
      $form_state->set('fixes',$existingFixes);
      $options = $this->fixDisplay ($existingFixes);
      //nlp_debug_msg('$options',$options);
      $form['old_fix'] = array(
        '#title' => 'Delete existing substitute values for HD/Pct',
        '#prefix' => " \n".'<div id="remove-fix" style="width:400px;">'." \n",
        '#suffix' => " \n".'</div>'." \n",
        '#type' => 'fieldset',
      );
      // Add a file upload file.
      $form['old_fix']['fix_delete'] = array(
        '#type' => 'checkboxes',
        '#title' => t('Select one or more substitutes to delete.'),
        '#options' => $options,
      );
      // Add a submit button.
      $form['old_fix']['fix_delete_submit'] = array(
        '#type' => 'submit',
        '#id' => 'fix_delete_submit',
        '#value' => 'Delete selected fixes',
        '#name' => 'fix_delete_submit',
      );
    }
    // Enter info for a new fix to HD and Pct.
    $form['new_fix'] = array(
      '#title' => 'Add substitute values for HD/Pct when missing',
      '#prefix' => " \n".'<div id="add-fix" style="width:400px;">'." \n",
      '#suffix' => " \n".'</div>'." \n",
      '#type' => 'fieldset',
    );
    // Description of add
    $form['new_fix']['fix_add_description'] = array(
      '#type' => 'item',
      '#markup' => 'Enter information to add substitute values for HD and Precinct.',
    );
    // MCID data entry field.
    $form['new_fix']['fix_mcid'] = array (
      '#title' => t('MCID'),
      '#size' => 11,
      '#type' => 'textfield',
    );
    // First name data entry field.
    $form['new_fix']['fix_first_name'] = array (
      '#title' => t('First Name'),
      '#size' => 40,
      '#type' => 'textfield',
    );
    // Last name data entry field.
    $form['new_fix']['fix_last_name'] = array (
      '#title' => t('Last Name'),
      '#size' => 40,
      '#type' => 'textfield',
    );
    // HD data entry field.
    $form['new_fix']['fix_hd'] = array (
      '#title' => t('HD'),
      '#size' => 5,
      '#type' => 'textfield',
    );
    // Pct data entry field.
    $form['new_fix']['fix_precinct'] = array (
      '#title' => t('Precinct'),
      '#size' => 20,
      '#type' => 'textfield',
    );
    // Add a submit button.
    $form['new_fix']['fix_add_submit'] = array(
      '#type' => 'submit',
      '#id' => 'fix_add_submit',
      '#value' => 'Add this fix',
      '#name' => 'fix_add_submit',
    );
    
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
    
    $values = $form_state->getValues();
    //nlp_debug_msg('$values',$values);
    switch ($element_clicked) {
      // Add a new fix for HD and Pct.
      case  'fix_add_submit':
        $fix['county'] = $form_state->get('county');
        $fix['mcid'] = trim(strip_tags(htmlentities(stripslashes($values['fix_mcid']),ENT_QUOTES)));
        $fix['firstName'] = trim(strip_tags(htmlentities(stripslashes($values['fix_first_name']),ENT_QUOTES)));
        $fix['lastName'] = trim(strip_tags(htmlentities(stripslashes($values['fix_last_name']),ENT_QUOTES)));
        $fix['hd'] = trim(strip_tags(htmlentities(stripslashes($values['fix_hd']),ENT_QUOTES)));
        $pctTrim = str_replace(' ', '', $values['fix_precinct']);
        $fix['precinct'] = trim(strip_tags(htmlentities(stripslashes($pctTrim),ENT_QUOTES)));
        $this->legFixes->createLegFix($fix);
        break;
      //  One or more fixes are to be deleted.
      case  'fix_delete_submit':
        // At least one was selected.
        //nlp_debug_msg('selections', $fs_selections);
        $existingFixes = $form_state->get('fixes');
        //nlp_debug_msg('fixes', $fs_fixes);
        foreach ($values['fix_delete'] as $selection) {
          if ($selection != '') {
            $fs_mcid = $existingFixes[$selection]['mcid'];
            $fs_county = $existingFixes[$selection]['county'];
            $this->legFixes->deleteLegFix($fs_county,$fs_mcid);
          }
        }
        break;
    }
  }
  
  /** * * * * * * * * * * * * * * * * * * * * * * * * * * * * * *
   * fixDisplay
   *
   * Convert the array of legislative district fixes to a set of strings.
   *
   * @param $fixes
   * @return array
   */
  function fixDisplay ($fixes): array
  {
    $fixDisplay = [];
    foreach ($fixes as $fix) {
      $fixDisplay[$fix['mcid']] = 'MCID ['.$fix['mcid'].
        '] '.$fix['firstName'].' '.$fix['lastName'].
        ' HD ['.$fix['hd'].'] PCT ['.$fix['precinct'].']';
    }
    return $fixDisplay;
  }
  
}
