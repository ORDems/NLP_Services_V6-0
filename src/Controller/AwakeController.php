<?php

namespace Drupal\nlpservices\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\nlpservices\AwakeSalutation;
use Symfony\Component\DependencyInjection\ContainerInterface;

class AwakeController extends ControllerBase {
  
  protected $salutation;
  protected $exportJobs;
  protected $renderTestObj;

  /**
   * {@inheritdoc}
   */
  public function __construct(AwakeSalutation $salutation, $exportJobs, $renderTestObj) {
    $this->salutation = $salutation;
    $this->exportJobs = $exportJobs;
    $this->renderTestObj = $renderTestObj;
  }
  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('nlpservices.awake'),
      $container->get('nlpservices.export_jobs'),
      $container->get('nlpservices.render_test'),
    );
  }
  
  public function awake() {
    return [
      '#markup' => $this->salutation->getSalutation(),
    ];
  }

  public function render_test() {
    return $this->renderTestObj->getRenderable();
  }
  
  public function webhook_callback() {
    $eventId = filter_input(INPUT_GET,'eventId',FILTER_SANITIZE_STRING);
    $this->exportJobs->endExportJob($eventId);
    return [];
  }
  
}
