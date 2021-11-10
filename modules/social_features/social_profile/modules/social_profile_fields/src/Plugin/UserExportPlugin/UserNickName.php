<?php

namespace Drupal\social_profile_fields\Plugin\UserExportPlugin;

use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\social_user_export\Plugin\UserExportPluginBase;
use Drupal\user\UserInterface;

/**
 * Provides a 'UserNickName' user export row.
 *
 * @UserExportPlugin(
 *  id = "user_nickname",
 *  label = @Translation("Nickname"),
 *  weight = -449,
 * )
 */
class UserNickName extends UserExportPluginBase {

  /**
   * {@inheritdoc}
   */
  public function getHeader(): TranslatableMarkup {
    return $this->t('Nickname');
  }

  /**
   * {@inheritdoc}
   */
  public function getValue(UserInterface $entity): string {
    return $this->profileGetFieldValue('field_profile_nick_name', $this->getProfile($entity));
  }

}
