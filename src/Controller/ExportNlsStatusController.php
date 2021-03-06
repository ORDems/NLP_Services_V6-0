<?php

namespace Drupal\nlpservices\Controller;

use Drupal;
use Drupal\Core\Controller\ControllerBase;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\nlpservices\NlpExportNlsStatus;
use Drupal\nlpservices\NlpExportTurfStatus;
use Drupal\nlpservices\NlpExportAwardStatus;

/**
 * @noinspection PhpUnused
 */
class ExportNlsStatusController extends ControllerBase
{
  //protected NlpExportNlsStatus $exportNlsStatus;
  protected NlpExportNlsStatus $exportNlsStatus;
  protected NlpExportTurfStatus $exportTurfStatus;
  protected NlpExportAwardStatus $exportAwardStatus;

  public function __construct( $exportNlsStatus,$exportTurfStatus,$exportAwardStatus) {
    $this->exportNlsStatus = $exportNlsStatus;
    $this->exportTurfStatus = $exportTurfStatus;
    $this->exportAwardStatus = $exportAwardStatus;
  }
  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container): ExportNlsStatusController
  {
    return new static(
      $container->get('nlpservices.export_nls_status'),
      $container->get('nlpservices.export_turf_status'),
      $container->get('nlpservices.export_award_status'),
    );
  }

  /** @noinspection PhpUnused */
  public function export_nls_status(): array
  {
    //nlp_debug_msg('export_nls_status');
    Drupal::service("page_cache_kill_switch")->trigger();
    return [
      '#markup' => $this->exportNlsStatus->getNlsStatusFile(FALSE),
    ];
  }

  /** @noinspection PhpUnused */
  public function export_turf_status(): array
  {
    Drupal::service("page_cache_kill_switch")->trigger();
    return [
      '#markup' => $this->exportTurfStatus->getTurfStatus(),
    ];
  }

  /** @noinspection PhpUnused */
  public function export_award_status(): array
  {
    Drupal::service("page_cache_kill_switch")->trigger();
    return [
      '#markup' => $this->exportAwardStatus->getAwardStatus(),
    ];
  }

  
}