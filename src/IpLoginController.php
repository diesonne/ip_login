<?php

namespace Drupal\ip_login;

use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Entity\Sql\SqlContentEntityStorage;
use Drupal\Core\Session\AccountInterface;
use Symfony\Component\HttpFoundation\Request;
use Drupal\user\Entity\User;
use Drupal\Core\Url;

class IpLoginController extends ControllerBase {

  /**
   * Menu callback for IP-based login: do the actual login.
   *
   * @return \Symfony\Component\HttpFoundation\RedirectResponse
   *   A redirect response object that may be returned by the controller.
   */
  public function loginProcess(Request $request) {
    $uid = $this->checkIpLoginExists($request);

    if (empty($uid)) {
      \Drupal::logger('ip_login')->warning('IP login processing accessed without any matches from @ip.',
        [
          '@ip' => $request->getClientIp(),
        ]);
    }
    else {
      static::doUserLogin($uid, $request);
    }

    $destination = Url::fromUserInput(\Drupal::destination()->get());

    if ($destination->isRouted()) {
      // Valid internal path.
      return $this->redirect(
        $destination->getRouteName(),
        $destination->getRouteParameters()
      );
    }
    else {
      return $this->redirect('<front>');
    }
  }

  /**
   * Logs in a user.
   *
   * @param int|string $uid
   *   A valid user ID.
   * @param \Symfony\Component\HttpFoundation\Request $request
   *   The current request object.
   */
  public static function doUserLogin($uid, Request $request) {
    $user = User::load($uid);
    user_login_finalize($user);

    \Drupal::logger('ip_login')
      ->notice('Logging in user @uid through IP login from @ip.',
        [
          '@uid' => $uid,
          '@ip' => $request->getClientIp(),
        ]);

    \Drupal::messenger()->addMessage(t('You have been logged in automatically using IP login.'));
  }

  /**
   * Looks up if current request IP matches an IP login.
   *
   * @param \Symfony\Component\HttpFoundation\Request $request
   *   A request object.
   *
   * @return string|false
   *   Returns the user ID if the IP address was matched, FALSE otherwise.
   */
  public static function checkIpLoginExists(Request $request) {
    $ip = inet_pton($request->getClientIp());

    // This query is done super early in the request (before page cache), so we
    // can optimize the majority case when the entity type is using core's
    // default storage handler, and do a straight database query.
    $entity_type = \Drupal::entityTypeManager()->getDefinition('user');
    if (is_subclass_of($entity_type->getStorageClass(), SqlContentEntityStorage::class)) {
      $result = \Drupal::database()->select('users_field_data', 'ufd')
        ->fields('ufd', ['uid'])
        ->condition('ip_login__ip_start', $ip, '<=')
        ->condition('ip_login__ip_end', $ip, '>=')
        ->condition('status', 1)
        ->orderBy('uid', 'DESC')
        ->range(0, 1)
        ->execute()
        ->fetchCol();
      $uid = reset($result);
    }
    else {
      $query = \Drupal::entityQuery('user')
        ->condition('ip_login.ip_start', $ip, '<=')
        ->condition('ip_login.ip_end', $ip, '>=')
        ->condition('status', 1);
      $uids = $query->execute();
      $uid = reset($uids);
    }

    return $uid;
  }

  /**
   * Checks whether a user can log into another account.
   *
   * @param \Drupal\Core\Session\AccountInterface $user
   *   The user account.
   *
   * @return bool
   *   Returns TRUE if the given user can login into another account, FALSE
   *   otherwise.
   */
  public static function canLoginAsAnotherUser(AccountInterface $user) {
    // People who can administer this module can.
    if ($user->hasPermission('administer ip login')) {
      return TRUE;
    }

    // If the user doesn't have a matching IP, then we let them log in normally.
    if (!self::checkIpLoginExists(\Drupal::request())) {
      return TRUE;
    }

    // For all other users check the correct permission.
    return $user->hasPermission('can log in as another user');
  }

}
