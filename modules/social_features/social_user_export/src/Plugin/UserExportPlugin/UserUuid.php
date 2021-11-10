<?php

namespace Drupal\social_user_export\Plugin\UserExportPlugin;

use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\social_user_export\Plugin\UserExportPluginBase;
use Drupal\user\UserInterface;

/**
 * Provides a 'UserUuid' user export row.
 *
 * @UserExportPlugin(
 *  id = "uuid",
 *  label = @Translation("UUID"),
 *  weight = -490,
 * )
 */
class UserUuid extends UserExportPluginBase {

  /**
   * {@inheritdoc}
   */
  public function getHeader(): TranslatableMarkup {
    return $this->t('UUID');
  }

  /**
   * {@inheritdoc}
   */
  public function getValue(UserInterface $entity): string {
    return $entity->uuid();
  }

}
