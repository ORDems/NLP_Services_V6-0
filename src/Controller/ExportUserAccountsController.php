<?php

namespace Drupal\nlpservices\Controller;

use Drupal\Core\Controller\ControllerBase;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\nlpservices\NlpExportUserAccounts;

class ExportUserAccountsController extends ControllerBase
{
  protected NlpExportUserAccounts $exportUserAccounts;

  public function __construct( $exportUserAccounts) {
    $this->exportUserAccounts = $exportUserAccounts;
  }
  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container): ExportUserAccountsController
  {
    return new static(
      $container->get('nlpservices.export_user_accounts'),
    );
  }

  public function createUserAccountsFile(): array
  {
    return [
      '#markup' => $this->exportUserAccounts->getUserAccountsFile(),
    ];
  }
}