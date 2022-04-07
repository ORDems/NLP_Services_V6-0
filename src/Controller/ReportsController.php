<?php

namespace Drupal\nlpservices\Controller;

use Drupal;
use Drupal\Core\Controller\ControllerBase;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\nlpservices\NlpResultsSummary;

/**
 * @noinspection PhpUnused
 */
class ReportsController extends ControllerBase
{
  protected NlpResultsSummary $resultsSummaryObj;

  public function __construct( $resultsSummaryObj) {
    $this->resultsSummaryObj = $resultsSummaryObj;
  }
  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container): ReportsController
  {
    return new static(
      $container->get('nlpservices.results_summary'),
    );
  }

  public function reports(): array
  {
    $template_path = drupal_get_path('module', 'nlpservices') . "/src/Templates/reports.html.twig";
    $template = file_get_contents($template_path);
    $variables = ['module' => 'nlpservices',];
    Drupal::service("page_cache_kill_switch")->trigger();
    return
      [
        'description' => [
          '#type' => 'inline_template',
          '#template' => $template,
          '#context' => $variables,
        ],
      ];
  }

  /** @noinspection PhpUnused */
  public function resultsSummary(): array
  {
    Drupal::service("page_cache_kill_switch")->trigger();
    return $this->resultsSummaryObj->displayResultsSummary();
  }

}