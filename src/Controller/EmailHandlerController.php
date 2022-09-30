<?php /** @noinspection PhpUnused */

namespace Drupal\nlpservices\Controller;

use Drupal;
use Drupal\Core\Controller\ControllerBase;
use Exception;
use Symfony\Component\DependencyInjection\ContainerInterface;

use Drupal\nlpservices\NlpReplies;

class EmailHandlerController extends ControllerBase
{
  protected NlpReplies $repliesObj;
  
  public function __construct( $repliesObj ) {
    $this->repliesObj = $repliesObj;
  }
  
  /**
   * {@inheritdoc}
   * @noinspection PhpMissingReturnTypeInspection
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('nlpservices.email_replies'),
    );
  }
  
  /** @noinspection PhpUnused */
  public function email_replies(): array
  {
    Drupal::service("page_cache_kill_switch")->trigger();
    try {
      $emailStatus = $this->repliesObj->emailForward();
    }
    catch (Exception $e) {
      return ['#markup' => 'huh', ];
    }
    return ['#markup' => $emailStatus, ];
  }
}