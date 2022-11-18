<?php /** @noinspection PhpUnused */

namespace Drupal\nlpservices\Controller;

use Drupal;
use Drupal\Core\Controller\ControllerBase;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\Core\TempStore\PrivateTempStoreFactory;
use Drupal\nlpservices\NlpDataEntryHelper;
use Drupal\nlpservices\NlpDataEntryPrint;

class DataEntryHelperController extends ControllerBase
{
  
  protected NlpDataEntryHelper $dataEntryHelper;
  protected NlpDataEntryPrint $dataEntryPrint;
  protected PrivateTempStoreFactory $tempStore;

  public function __construct( $dataEntryHelper, $dataEntryPrint, $tempStore) {
    $this->dataEntryHelper = $dataEntryHelper;
    $this->dataEntryPrint = $dataEntryPrint;
    $this->tempStore = $tempStore;
  }
  
  /**
   * {@inheritdoc}
   * @noinspection PhpMissingReturnTypeInspection
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('nlpservices.data_entry_helper'),
      $container->get('nlpservices.data_entry_print'),
      $container->get('tempstore.private')
    );
  }

  /** @noinspection PhpUnused */
  public function data_entry_helper(): array
  {
    $output = [
      '#markup' => $this->dataEntryHelper->dataEntryHelp(),
    ];
    $output['#attached']['library'][] = 'nlpservices/data-entry-help';
    //nlp_debug_msg('$output',$output);
    return $output;
  }

  public function about(): array
  {
    Drupal::service("page_cache_kill_switch")->trigger();
    return  [
      '#markup' => '<p style="font-size: large;">NLP Services Version 6.1</p>',
    ];
  }
  
  public function data_entry_print(): array
  {
    Drupal::service("page_cache_kill_switch")->trigger();
    $output = [
      '#markup' => $this->dataEntryPrint->dataEntryPrint(),
    ];
    $output['#attached']['library'][] = 'nlpservices/data-entry-help';
    //nlp_debug_msg('$output',$output);
    return $output;
  }

  public function printable_calling_page($turfIndex): array
  {
    $voters = $this->dataEntryPrint->getVoters($turfIndex);
    $page = $this->dataEntryPrint->buildCallList($voters);
    Drupal::service("page_cache_kill_switch")->trigger();
    $output = [
      '#markup' => $page,
      '#cache' => [
        'contexts' => ['url'],
        'max-age' => 0,
      ],
    ];
    $output['#attached']['library'][] = 'nlpservices/data-entry-help';
    //nlp_debug_msg('$output',$output);

    return $output;
  }

  public function printable_mailing_page($turfIndex): array
  {
    $voters = $this->dataEntryPrint->getVoters($turfIndex);
    $page = $this->dataEntryPrint->buildMailingList($voters);
    Drupal::service("page_cache_kill_switch")->trigger();
    $output = [
      '#markup' => $page,
      '#cache' => [
        'contexts' => ['url'],
        'max-age' => 0,
      ],
    ];
    $output['#attached']['library'][] = 'nlpservices/data-entry-help';
    return $output;
  }
}
