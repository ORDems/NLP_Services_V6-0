<?php /** @noinspection PhpParamsInspection */

namespace Drupal\nlpservices\Form;

use Drupal;
use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Config\ConfigFactoryInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * @noinspection PhpUnused
 */
class CountyChangeForm extends ConfigFormBase {

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
    return 'county_change_form';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state): array
  {

    $config = $this->config('nlpservices.configuration');
    $counties = $config->get('nlpservices-county-names');
    //nlp_debug_msg('$counties',$counties);

    $factory = Drupal::service('tempstore.private');
    $store = $factory->get('nlpservices.session_data');
    $county = $store->get('County');
    $form_state->set('county',$county);

    $form['county-name'] = [
      '#markup' => "<h1>".$county." County</h1>",
    ];

    $countyNames = array_keys($counties);
    $countyNames[0] = "Select County";
    $form_state->set('countyNames',$countyNames);
    $form['county_select'] = [
      '#type' => 'select',
      '#title' => $this->t('New County'),
      '#description' => $this->t('Please select a county that you want
      to access.  This change persists until you logout or change it again.'),
      '#options' => $countyNames,
    ];

    return parent::buildForm($form, $form_state);
  }


  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {

    $values = $form_state->getValues();
    //nlp_debug_msg('$values',$values);
    //$county = '';
    if(!empty($values['county_select'])) {
      $selectedCountyIndex = $values['county_select'];
      $countyNames = $form_state->get('countyNames');
      $county = $countyNames[$selectedCountyIndex];
    } else {
      $messenger = Drupal::messenger();
      $messenger->addError('Select a county.');
      return;
    }
    $factory = Drupal::service('tempstore.private');
    $store = $factory->get('nlpservices.session_data');
    //$county = $store->get('County');
    $store->set('County',$county);
    
    try {
      $store->set('currentFolderId', 0);
    } catch (Drupal\Core\TempStore\TempStoreException $e) {
      nlp_debug_msg('Temp store save error',$e->getMessage());
    }
  
    try {
      $store->set('currentHd', NULL);
      $store->set('currentPct', NULL);
    } catch (Drupal\Core\TempStore\TempStoreException $e) {
      nlp_debug_msg('Temp store save error',$e->getMessage());
    }

    parent::submitForm($form, $form_state);
  }

}